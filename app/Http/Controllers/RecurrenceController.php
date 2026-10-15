<?php

namespace App\Http\Controllers;

use App\Actions\Concierge\NotifyActivity;
use App\Actions\Concierge\PrepareRecurringMissions;
use App\Actions\Concierge\QuotaGate;
use App\Actions\Concierge\ScheduleGuard;
use App\Actions\Concierge\WorkspaceAccess;
use App\Models\Concierge;
use App\Models\Mission;
use App\Models\MissionRecurrence;
use App\Models\RecurrenceOccurrence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecurrenceController extends Controller
{
    public function __construct(private WorkspaceAccess $access, private PrepareRecurringMissions $prepare, private QuotaGate $quotas, private ScheduleGuard $schedule, private NotifyActivity $notifications) {}

    public function store(Request $request): RedirectResponse
    {
        $member = $this->access->manager($request);
        $data = $request->validate(['property_id' => ['required', Rule::exists('properties', 'id')->where('concierge_id', $member->concierge_id)->whereNull('archived_at')], 'template_id' => ['required', Rule::exists('service_templates', 'id')->where('concierge_id', $member->concierge_id)->whereNull('archived_at')], 'assigned_user_id' => ['nullable', 'integer', Rule::exists('memberships', 'user_id')->where('concierge_id', $member->concierge_id)->whereIn('role', ['manager', 'worker'])], 'title' => ['required', 'string', 'max:120'], 'kind' => ['required', Rule::in(['service', 'initial', 'arrival', 'departure'])], 'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])], 'interval' => ['required', 'integer', 'min:1', 'max:12'], 'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now('Europe/Paris')->toDateString()], 'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'], 'time' => ['required', 'date_format:H:i'], 'duration' => ['required', 'integer', 'min:5', 'max:1440'], 'instructions' => ['nullable', 'string', 'max:5000']]);
        DB::transaction(function () use ($request, $member, $data) {
            Concierge::whereKey($this->access->membership($request)->concierge_id)->lockForUpdate()->firstOrFail();
            MissionRecurrence::create([...$data, 'concierge_id' => $member->concierge_id, 'next_on' => $data['starts_on']]);
            $this->prepare->handle($member->concierge_id);
            $this->access->log($request, 'Récurrence créée : '.$data['title']);
        });

        return back()->with('success', 'Récurrence enregistrée. Les propositions attendent votre confirmation.');
    }

    public function toggle(Request $request, int $recurrence): RedirectResponse
    {
        $member = $this->access->manager($request);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($member, $recurrence, $data, $request) {
            Concierge::whereKey($this->access->membership($request)->concierge_id)->lockForUpdate()->firstOrFail();
            Concierge::whereKey($member->concierge_id)->lockForUpdate()->firstOrFail();
            $rule = MissionRecurrence::where('concierge_id', $member->concierge_id)->lockForUpdate()->findOrFail($recurrence);
            if ($data['is_active']) {
                abort_unless(! $rule->property->archived_at && ! $rule->template->archived_at, 409, 'Restaurez le lieu et la prestation avant de reprendre cette récurrence.');
            }
            $rule->update($data);
            $this->access->log($request, ($data['is_active'] ? 'Récurrence reprise : ' : 'Récurrence suspendue : ').$rule->title);
        });
        if ($data['is_active']) {
            $this->prepare->handle($member->concierge_id);
        }

        return back()->with('success', 'Récurrence mise à jour.');
    }

    public function generate(Request $request): RedirectResponse
    {
        $member = $this->access->manager($request);
        $count = $this->prepare->handle($member->concierge_id);

        return back()->with('success', $count.' nouvelle(s) proposition(s) préparée(s).');
    }

    public function decide(Request $request, int $occurrence): RedirectResponse
    {
        $member = $this->access->manager($request);
        $data = $request->validate(['decision' => ['required', Rule::in(['confirmed', 'skipped'])]]);
        $this->quotas->mutate($request, $data['decision'] === 'confirmed' ? ['missions_month' => 1] : [], function () use ($member, $occurrence, $data, $request) {
            $record = RecurrenceOccurrence::where('concierge_id', $member->concierge_id)->lockForUpdate()->findOrFail($occurrence);
            abort_unless($record->status === 'pending', 409, 'Cette proposition a déjà été traitée.');
            $mission = null;
            if ($data['decision'] === 'confirmed') {
                abort_unless($record->recurrence->is_active, 409, 'Cette récurrence est suspendue.');
                abort_unless(! $record->recurrence->property->archived_at && ! $record->recurrence->template->archived_at, 409, 'Le lieu ou la prestation est archivé.');
                $snapshot = $record->snapshot;
                $this->schedule->check($member->concierge_id, $snapshot['assigned_user_id'], Carbon::parse($snapshot['scheduled_at']), $snapshot['duration']);
                $mission = Mission::create([...$snapshot, 'concierge_id' => $member->concierge_id]);
                $this->notifications->assigned($mission, $request->user()->id);
            }
            $record->update(['status' => $data['decision'], 'mission_id' => $mission?->id]);
            $this->access->log($request, ($mission ? 'Proposition récurrente confirmée' : 'Proposition récurrente ignorée'), $mission?->id);
        });

        return back()->with('success', 'Proposition traitée.');
    }
}

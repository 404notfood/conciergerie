<?php

namespace App\Http\Controllers;

use App\Models\Concierge;

use App\Actions\Concierge\NotifyActivity;
use App\Actions\Concierge\QuotaGate;
use App\Actions\Concierge\ScheduleGuard;
use App\Actions\Concierge\WorkspaceAccess;
use App\Models\Incident;
use App\Models\IncidentEvent;
use App\Models\Mission;
use App\Models\Photo;
use App\Models\ServiceTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncidentWorkflowController extends Controller
{
    public function __construct(private WorkspaceAccess $access, private QuotaGate $quotas, private ScheduleGuard $schedule, private NotifyActivity $notifications) {}

    public function update(Request $request, int $incident): RedirectResponse
    {
        $member = $this->access->manager($request);
        $data = $request->validate(['version' => ['required', 'integer'], 'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])], 'responsible_user_id' => ['nullable', 'integer', Rule::exists('memberships', 'user_id')->where('concierge_id', $member->concierge_id)->whereIn('role', ['manager', 'worker'])], 'note' => ['nullable', 'string', 'max:3000']]);
        DB::transaction(function () use ($request, $incident, $member, $data) {
            Concierge::whereKey($this->access->membership($request)->concierge_id)->lockForUpdate()->firstOrFail();
            $record = Incident::where('concierge_id', $member->concierge_id)->lockForUpdate()->findOrFail($incident);
            abort_unless($record->version === (int) $data['version'], 409, 'Ce dossier a changé. Actualisez la page.');
            abort_if($record->status === 'resolved', 409, 'Rouvrez le dossier avant de modifier son suivi.');
            $record->update(['priority' => $data['priority'], 'responsible_user_id' => $data['responsible_user_id'], 'status' => $data['responsible_user_id'] ? 'in_progress' : 'open', 'version' => $record->version + 1]);
            $this->event($request, $record, 'assigned', $data['note'] ?: 'Responsable et priorité mis à jour.');
            $this->notify($record, $request->user()->id);
        });

        return back()->with('success', 'Suivi de l’incident mis à jour.');
    }

    public function repair(Request $request, int $incident): RedirectResponse
    {
        $member = $this->access->manager($request);
        $data = $request->validate(['version' => ['required', 'integer'], 'template_id' => ['required', Rule::exists('service_templates', 'id')->where('concierge_id', $member->concierge_id)], 'assigned_user_id' => ['nullable', 'integer', Rule::exists('memberships', 'user_id')->where('concierge_id', $member->concierge_id)->whereIn('role', ['manager', 'worker'])], 'scheduled_at' => ['required', 'date'], 'duration' => ['required', 'integer', 'min:5', 'max:1440']]);
        $this->quotas->mutate($request, ['missions_month' => 1], function () use ($request, $incident, $member, $data) {
            $record = Incident::where('concierge_id', $member->concierge_id)->lockForUpdate()->findOrFail($incident);
            abort_unless($record->version === (int) $data['version'] && $record->status !== 'resolved', 409, 'Ce dossier a changé ou est déjà clos.');
            if ($record->repair_mission_id) {
                abort_unless(Mission::whereKey($record->repair_mission_id)->where('status', 'cancelled')->exists(), 409, 'Une intervention corrective est déjà liée à ce dossier.');
            }
            $start = Carbon::parse($data['scheduled_at'], 'Europe/Paris')->utc();
            $this->schedule->check($member->concierge_id, $data['assigned_user_id'] ?? null, $start, $data['duration']);
            $template = ServiceTemplate::whereKey($data['template_id'])->firstOrFail();
            $mission = Mission::create(['concierge_id' => $member->concierge_id, 'property_id' => $record->mission->property_id, 'assigned_user_id' => $data['assigned_user_id'] ?? null, 'title' => 'Correction de l’incident #'.$record->id, 'scheduled_at' => $start, 'duration' => $data['duration'], 'instructions' => $record->description, 'checklist' => $template->checklist]);
            $record->update(['repair_mission_id' => $mission->id, 'responsible_user_id' => $data['assigned_user_id'] ?? $record->responsible_user_id, 'status' => 'in_progress', 'version' => $record->version + 1]);
            $this->event($request, $record, 'repair_created', 'Intervention corrective planifiée : mission #'.$mission->id.'.');
            $this->access->log($request, 'Intervention corrective créée pour l’incident #'.$record->id, $mission->id);
            $this->notifications->assigned($mission, $request->user()->id);
            $this->notify($record, $request->user()->id);
        });

        return back()->with('success', 'Intervention corrective créée. Elle suit le circuit habituel d’acceptation et de contrôle.');
    }

    public function resolve(Request $request, int $incident): RedirectResponse
    {
        $member = $this->access->manager($request);
        $data = $request->validate(['version' => ['required', 'integer'], 'resolution_notes' => ['required', 'string', 'min:5', 'max:5000'], 'resolution_photo_ids' => ['present', 'array', 'max:20'], 'resolution_photo_ids.*' => ['integer', 'distinct']]);
        DB::transaction(function () use ($request, $incident, $member, $data) {
            Concierge::whereKey($this->access->membership($request)->concierge_id)->lockForUpdate()->firstOrFail();
            $record = Incident::where('concierge_id', $member->concierge_id)->lockForUpdate()->findOrFail($incident);
            abort_unless($record->version === (int) $data['version'] && $record->status !== 'resolved', 409, 'Ce dossier a changé ou est déjà clos.');
            if ($record->repair_mission_id && ! Mission::whereKey($record->repair_mission_id)->where('status', 'validated')->exists()) {
                throw ValidationException::withMessages(['resolution_notes' => 'Validez le compte rendu de l’intervention corrective avant de clôturer ce dossier.']);
            }
            foreach ($data['resolution_photo_ids'] as $photoId) {
                Photo::where('concierge_id', $member->concierge_id)->whereHas('report', fn ($query) => $query->where('status', 'validated')->where('mission_id', $record->repair_mission_id ?: $record->mission_id))->findOrFail($photoId);
            }
            $record->update([...$data, 'status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $request->user()->id, 'version' => $record->version + 1]);
            $this->event($request, $record, 'resolved', $data['resolution_notes']);
            $this->access->log($request, 'Incident clôturé : #'.$record->id, $record->mission_id);
            $this->notify($record, $request->user()->id);
        });

        return back()->with('success', 'Incident clôturé et résolution documentée. Vous pouvez transmettre le bilan au client.');
    }

    public function reopen(Request $request, int $incident): RedirectResponse
    {
        $member = $this->access->manager($request);
        $data = $request->validate(['version' => ['required', 'integer'], 'note' => ['required', 'string', 'min:5', 'max:3000']]);
        DB::transaction(function () use ($request, $member, $incident, $data) {
            Concierge::whereKey($this->access->membership($request)->concierge_id)->lockForUpdate()->firstOrFail();
            $record = Incident::where('concierge_id', $member->concierge_id)->lockForUpdate()->findOrFail($incident);
            abort_unless($record->version === (int) $data['version'] && $record->status === 'resolved', 409);
            $record->update(['status' => 'open', 'resolved_at' => null, 'resolved_by' => null, 'resolution_notes' => null, 'resolution_photo_ids' => null, 'version' => $record->version + 1]);
            $this->event($request, $record, 'reopened', $data['note']);
            $this->notify($record, $request->user()->id);
        });

        return back()->with('success', 'Dossier rouvert. Son historique est conservé.');
    }

    private function event(Request $request, Incident $record, string $action, string $description): void
    {
        IncidentEvent::create(['concierge_id' => $record->concierge_id, 'incident_id' => $record->id, 'user_id' => $request->user()->id, 'action' => $action, 'description' => $description]);
    }

    private function notify(Incident $record, int $actorId): void
    {
        $this->notifications->send($record->concierge_id, $this->notifications->managers($record->concierge_id), 'incident_updated', 'incident:'.$record->id.':'.$record->version, 'Le suivi d’un incident a été mis à jour.', 'incidents', $actorId);
    }
}

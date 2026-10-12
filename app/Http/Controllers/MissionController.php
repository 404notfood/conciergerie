<?php

namespace App\Http\Controllers;

use App\Actions\Concierge\NotifyActivity;
use App\Actions\Concierge\QuotaGate;
use App\Actions\Concierge\ScheduleGuard;
use App\Actions\Concierge\SubmitReport;
use App\Actions\Concierge\WorkspaceAccess;
use App\Models\Concierge;
use App\Models\Incident;
use App\Models\IncidentEvent;
use App\Models\Membership;
use App\Models\Mission;
use App\Models\Photo;
use App\Models\Report;
use App\Models\ServiceTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MissionController extends Controller
{
    public function __construct(private WorkspaceAccess $access, private QuotaGate $quotas, private ScheduleGuard $schedule, private NotifyActivity $notifications) {}

    public function store(Request $request): RedirectResponse
    {
        $member = $this->access->manager($request);
        $data = $request->validate(['property_id' => ['required', Rule::exists('properties', 'id')->where('concierge_id', $member->concierge_id)], 'assigned_company' => ['nullable', 'string', 'max:120', Rule::exists('memberships', 'company')->where('concierge_id', $member->concierge_id)->where('role', 'coordinator')], 'assigned_user_id' => ['nullable', Rule::prohibitedIf($request->filled('assigned_company')), Rule::exists('memberships', 'user_id')->where('concierge_id', $member->concierge_id)->whereIn('role', ['manager', 'worker'])], 'template_id' => ['required', Rule::exists('service_templates', 'id')->where('concierge_id', $member->concierge_id)], 'title' => ['required', 'string', 'max:120'], 'kind' => ['required', Rule::in(['service', 'initial', 'arrival', 'departure'])], 'scheduled_at' => ['required', 'date'], 'duration' => ['required', 'integer', 'min:5', 'max:1440'], 'instructions' => ['nullable', 'string', 'max:5000']]);
        if ($data['assigned_company'] ?? false) {
            $this->quotas->feature($request, 'subcontracting');
        }
        $data['scheduled_at'] = Carbon::parse($data['scheduled_at'], 'Europe/Paris')->utc();
        $template = ServiceTemplate::whereKey($data['template_id'])->firstOrFail();
        unset($data['template_id']);
        $this->quotas->mutate($request, ['missions_month' => 1], function () use ($request, $data, $member, $template) {
            $this->schedule->check($member->concierge_id, $data['assigned_user_id'] ?? null, $data['scheduled_at'], $data['duration']);
            $mission = Mission::create([...$data, 'concierge_id' => $member->concierge_id, 'checklist' => $template->checklist]);
            $this->notifications->assigned($mission, $request->user()->id);
            $this->access->log($request, 'Mission planifiée : '.$mission->title, $mission->id);
        });

        return back()->with('success', 'Mission planifiée.');
    }

    public function transition(Request $request, int $mission): RedirectResponse
    {
        $member = $this->access->membership($request);
        abort_unless(in_array($member->role, ['manager', 'worker'], true), 403);
        $data = $request->validate(['action' => ['required', Rule::in(['accept', 'start', 'cancel', 'reassign'])], 'version' => ['required', 'integer'], 'assigned_user_id' => ['nullable', 'required_if:action,reassign', Rule::exists('memberships', 'user_id')->where('concierge_id', $member->concierge_id)->whereIn('role', ['manager', 'worker'])]]);
        DB::transaction(function () use ($request, $mission, $member, $data) {
            Concierge::whereKey($this->access->membership($request)->concierge_id)->lockForUpdate()->firstOrFail();
            $record = Mission::where('concierge_id', $member->concierge_id)->lockForUpdate()->findOrFail($mission);
            abort_unless($member->role === 'manager' || $record->assigned_user_id === $request->user()->id, 404);
            abort_unless($record->version === (int) $data['version'], 409, 'La mission a changé. Actualisez la page.');
            $action = $data['action'];
            if (in_array($action, ['cancel', 'reassign'], true)) {
                abort_unless($member->role === 'manager', 403);
                abort_if(in_array($record->status, ['validated', 'cancelled', 'submitted'], true), 409);
            }
            if ($action === 'accept') {
                abort_unless($record->status === 'planned', 409);
                abort_unless($record->assigned_user_id === $request->user()->id, 403);
            }
            if ($action === 'start') {
                abort_unless($record->status === 'accepted', 409);
            }
            $status = ['accept' => 'accepted', 'start' => 'in_progress', 'cancel' => 'cancelled', 'reassign' => 'planned'][$action];
            if ($action === 'reassign') {
                $this->schedule->check($member->concierge_id, (int) $data['assigned_user_id'], $record->scheduled_at, $record->duration, $record->id);
            }
            $previousUser = $record->assigned_user_id;
            $record->update(['status' => $status, 'version' => $record->version + 1, ...($action === 'reassign' ? ['assigned_user_id' => $data['assigned_user_id'], 'assigned_company' => null] : [])]);
            if ($action === 'accept') {
                $this->notifications->send($member->concierge_id, $this->notifications->managers($member->concierge_id), 'mission_response', 'mission:'.$record->id.':'.$record->version, 'Une mission a été acceptée par son intervenant.', 'missions', $request->user()->id);
            }
            if ($action === 'reassign') {
                $this->notifications->assigned($record, $request->user()->id);
            }
            if (in_array($action, ['cancel', 'reassign'], true) && $previousUser) {
                $this->notifications->send($member->concierge_id, Membership::where('concierge_id', $member->concierge_id)->where('user_id', $previousUser)->get(), 'mission_assigned', 'change:'.$record->id.':'.$record->version, 'Une mission de votre planning a été annulée ou réaffectée.', 'missions', $request->user()->id);
            }
            $this->access->log($request, ['accept' => 'Mission acceptée', 'start' => 'Mission commencée', 'cancel' => 'Mission annulée', 'reassign' => 'Mission réaffectée'][$action].' : '.$record->title, $record->id);
        });

        return back()->with('success', 'Mission mise à jour.');
    }

    public function designate(Request $request, int $mission): RedirectResponse
    {
        $member = $this->access->membership($request);
        abort_unless($member->role === 'coordinator', 403);
        $this->quotas->feature($request, 'subcontracting');
        $data = $request->validate(['version' => ['required', 'integer'], 'assigned_user_id' => ['required', 'integer', Rule::exists('memberships', 'user_id')->where('concierge_id', $member->concierge_id)->where('company', $member->company)->where('role', 'worker')]]);
        DB::transaction(function () use ($request, $member, $mission, $data) {
            Concierge::whereKey($this->access->membership($request)->concierge_id)->lockForUpdate()->firstOrFail();
            $record = Mission::where('concierge_id', $member->concierge_id)->where('assigned_company', $member->company)->lockForUpdate()->findOrFail($mission);
            abort_unless($record->status === 'planned' && $record->version === (int) $data['version'], 409, 'La mission a changé.');
            $this->schedule->check($member->concierge_id, (int) $data['assigned_user_id'], $record->scheduled_at, $record->duration, $record->id);
            $record->update(['assigned_user_id' => $data['assigned_user_id'], 'version' => $record->version + 1]);
            $this->notifications->assigned($record, $request->user()->id);
            $this->access->log($request, 'Intervenant désigné par '.$member->company.' : '.$record->title, $record->id);
        });

        return back()->with('success', 'Intervenant désigné. Il doit maintenant accepter la mission.');
    }

    public function report(Request $request, int $mission, SubmitReport $submit): RedirectResponse|JsonResponse
    {
        $report = $submit->handle($request, $mission);

        return $request->expectsJson() ? response()->json(['received' => true, 'report_id' => $report->id]) : back()->with('success', 'Compte rendu reçu. Il attend la validation du responsable.');
    }

    public function review(Request $request, int $report): RedirectResponse
    {
        $this->access->manager($request);
        $data = $request->validate(['decision' => ['required', Rule::in(['validated', 'changes_requested'])], 'feedback' => ['nullable', 'required_if:decision,changes_requested', 'string', 'max:3000']]);
        DB::transaction(function () use ($request, $report, $data) {
            Concierge::whereKey($this->access->membership($request)->concierge_id)->lockForUpdate()->firstOrFail();
            $record = $this->access->record($request, Report::class, $report);
            $mission = Mission::whereKey($record->mission_id)->lockForUpdate()->firstOrFail();
            $record->refresh();
            abort_unless($record->status === 'submitted' && $mission->status === 'submitted' && $mission->reports()->latest('id')->first()->id === $record->id, 409);
            $record->update(['status' => $data['decision'], 'feedback' => $data['feedback'] ?? null, 'reviewer_id' => $request->user()->id, 'reviewed_at' => now()]);
            $mission->update(['status' => $data['decision'], 'version' => $mission->version + 1]);
            $this->notifications->send($mission->concierge_id, Membership::where('concierge_id', $mission->concierge_id)->where('user_id', $record->author_id)->get(), 'report_reviewed', 'report:'.$record->id, $data['decision'] === 'validated' ? 'Votre compte rendu a été validé.' : 'Une reprise est demandée pour votre compte rendu.', 'reports', $request->user()->id);
            if ($data['decision'] === 'validated') {
                $this->notifications->send($mission->concierge_id, Membership::where('concierge_id', $mission->concierge_id)->where('role', 'client')->where('client_id', $mission->property->client_id)->get(), 'report_reviewed', 'report:'.$record->id, 'Un compte rendu validé est disponible dans votre espace.', 'reports', $request->user()->id);
            }
            $this->access->log($request, ($data['decision'] === 'validated' ? 'Compte rendu validé et publié' : 'Reprise demandée').' : '.$mission->title, $mission->id);
        });

        return back()->with('success', 'Décision enregistrée.');
    }

    public function incident(Request $request, int $mission): RedirectResponse|JsonResponse
    {
        $member = $this->access->membership($request);
        abort_if($member->role === 'client', 403);
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'description' => ['required', 'string', 'max:5000'], 'version' => ['required', 'integer']]);
        $incident = DB::transaction(function () use ($request, $mission, $member, $data) {
            Concierge::whereKey($this->access->membership($request)->concierge_id)->lockForUpdate()->firstOrFail();
            $record = $this->access->mission($request, $mission);
            $record = Mission::whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_unless($member->role === 'manager' || $record->assigned_user_id === $request->user()->id, 404);
            $existing = Incident::where('concierge_id', $member->concierge_id)->where('author_id', $request->user()->id)->where('operation_id', $data['operation_id'])->first();
            if ($existing) {
                abort_unless($existing->mission_id === $record->id, 409);

                return $existing;
            }
            abort_unless($record->version === (int) $data['version'] && in_array($record->status, ['accepted', 'in_progress', 'changes_requested'], true), 409);
            $incident = Incident::create(['concierge_id' => $member->concierge_id, 'mission_id' => $record->id, 'author_id' => $request->user()->id, 'description' => $data['description'], 'operation_id' => $data['operation_id']]);
            IncidentEvent::create(['concierge_id' => $member->concierge_id, 'incident_id' => $incident->id, 'user_id' => $request->user()->id, 'action' => 'created', 'description' => 'Incident signalé.']);
            $this->notifications->send($member->concierge_id, $this->notifications->managers($member->concierge_id), 'incident_created', 'incident:'.$incident->id, 'Un incident a été signalé. Consultez son dossier pour organiser la suite.', 'incidents', $request->user()->id);
            $this->access->log($request, 'Incident signalé : '.$record->title, $record->id);

            return $incident;
        });

        return $request->expectsJson() ? response()->json(['received' => true, 'incident_id' => $incident->id]) : back()->with('success', 'Incident reçu par le responsable.');
    }

    public function shareIncident(Request $request, int $incident): RedirectResponse
    {
        $this->access->manager($request);
        $data = $request->validate(['client_message' => ['required', 'string', 'max:5000'], 'shared_photo_ids' => ['present', 'array', 'max:20'], 'shared_photo_ids.*' => ['integer'], 'status' => ['required', 'in:open,in_progress,resolved']]);
        DB::transaction(function () use ($request, $incident, $data) {
            Concierge::whereKey($this->access->membership($request)->concierge_id)->lockForUpdate()->firstOrFail();
            $record = $this->access->record($request, Incident::class, $incident);
            foreach ($data['shared_photo_ids'] as $photoId) {
                Photo::where('concierge_id', $record->concierge_id)->whereHas('report', fn ($q) => $q->where(fn ($reports) => $reports->where('mission_id', $record->mission_id)->orWhere(fn ($repair) => $repair->where('mission_id', $record->repair_mission_id)->where('status', 'validated'))))->findOrFail($photoId);
            }
            unset($data['status']);
            $record->update([...$data, 'shared_status' => $record->status, 'version' => $record->version + 1, 'shared_by' => $request->user()->id, 'shared_at' => now()]);
            IncidentEvent::create(['concierge_id' => $record->concierge_id, 'incident_id' => $record->id, 'user_id' => $request->user()->id, 'action' => 'shared', 'description' => 'Éléments sélectionnés transmis au client.']);
            $this->notifications->send($record->concierge_id, Membership::where('concierge_id', $record->concierge_id)->where('role', 'client')->where('client_id', $record->mission->property->client_id)->get(), 'incident_updated', 'shared:'.$record->id.':'.$record->version, 'Votre conciergerie a partagé une information sur un incident.', 'incidents', $request->user()->id);
            $this->access->log($request, 'Incident transmis au client : '.$record->id, $record->mission_id);
        });

        return back()->with('success', 'Les éléments sélectionnés ont été transmis au client.');
    }

    public function photo(Request $request, int $photo): StreamedResponse
    {
        $member = $this->access->membership($request);
        $record = $this->access->record($request, Photo::class, $photo);
        $report = $record->report;
        $mission = Mission::where('concierge_id', $member->concierge_id)->with('property')->whereKey($report->mission_id)->firstOrFail();
        if ($member->role === 'coordinator') {
            abort_unless($mission->assigned_company === $member->company, 404);
        }
        if ($member->role === 'worker') {
            abort_unless($mission->assigned_user_id === $request->user()->id, 404);
        }
        if ($member->role === 'client') {
            abort_unless($mission->property->client_id === $member->client_id, 404);
            $shared = Incident::where('concierge_id', $member->concierge_id)->where('mission_id', $mission->id)->whereNotNull('shared_at')->get()->contains(fn ($incident) => in_array($record->id, $incident->shared_photo_ids ?? [], true));
            abort_unless($report->status === 'validated' || $shared, 404);
        }

        return Storage::disk('local')->response($record->path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}

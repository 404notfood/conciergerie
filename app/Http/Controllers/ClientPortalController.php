<?php

namespace App\Http\Controllers;

use App\Actions\Concierge\QuotaGate;
use App\Actions\Concierge\WorkspaceAccess;
use App\Models\InterventionRequest;
use App\Models\Message;
use App\Models\Mission;
use App\Models\ServiceTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientPortalController extends Controller
{
    public function __construct(private WorkspaceAccess $access, private QuotaGate $quotas) {}

    public function message(Request $request): RedirectResponse
    {
        $member = $this->access->membership($request);
        abort_unless(in_array($member->role, ['manager', 'client'], true), 403);
        $this->quotas->feature($request, 'messaging');
        $data = $request->validate(['client_id' => ['required', Rule::exists('clients', 'id')->where('concierge_id', $member->concierge_id)], 'body' => ['required', 'string', 'max:5000']]);
        if ($member->role === 'client') {
            abort_unless((int) $data['client_id'] === $member->client_id, 404);
        }
        Message::create([...$data, 'concierge_id' => $member->concierge_id, 'author_id' => $request->user()->id]);

        return back()->with('success', 'Message envoyé.');
    }

    public function request(Request $request): RedirectResponse
    {
        $member = $this->access->membership($request);
        abort_unless($member->role === 'client', 403);
        $this->quotas->feature($request, 'client_portal');
        $data = $request->validate(['property_id' => ['required', Rule::exists('properties', 'id')->where('concierge_id', $member->concierge_id)->where('client_id', $member->client_id)], 'title' => ['required', 'string', 'max:120'], 'description' => ['required', 'string', 'max:5000']]);
        DB::transaction(function () use ($request, $member, $data) {
            InterventionRequest::create([...$data, 'concierge_id' => $member->concierge_id, 'author_id' => $request->user()->id]);
            $this->access->log($request, 'Demande client reçue : '.$data['title']);
        });

        return back()->with('success', 'Demande reçue. Votre conciergerie doit la valider avant toute intervention.');
    }

    public function decide(Request $request, int $interventionRequest): RedirectResponse
    {
        $member = $this->access->manager($request);
        $data = $request->validate(['decision' => ['required', 'in:accepted,rejected'], 'feedback' => ['required', 'string', 'max:3000'], 'scheduled_at' => ['nullable', 'required_if:decision,accepted', 'date'], 'template_id' => ['nullable', 'required_if:decision,accepted', Rule::exists('service_templates', 'id')->where('concierge_id', $member->concierge_id)], 'assigned_user_id' => ['nullable', Rule::exists('memberships', 'user_id')->where('concierge_id', $member->concierge_id)->whereIn('role', ['manager', 'worker'])]]);
        DB::transaction(function () use ($request, $interventionRequest, $member, $data) {
            $record = InterventionRequest::where('concierge_id', $member->concierge_id)->lockForUpdate()->findOrFail($interventionRequest);
            abort_unless($record->status === 'pending', 409);
            $missionId = null;
            if ($data['decision'] === 'accepted') {
                $template = ServiceTemplate::whereKey($data['template_id'])->firstOrFail();
                $mission = $this->quotas->mutate($request, ['missions_month' => 1], fn () => Mission::create(['concierge_id' => $member->concierge_id, 'property_id' => $record->property_id, 'assigned_user_id' => $data['assigned_user_id'] ?? null, 'title' => $record->title, 'scheduled_at' => Carbon::parse($data['scheduled_at'], 'Europe/Paris')->utc(), 'instructions' => $record->description, 'checklist' => $template->checklist]));
                $missionId = $mission->id;
            }
            $record->update(['status' => $data['decision'], 'decision' => $data['feedback'], 'mission_id' => $missionId]);
            $this->access->log($request, 'Demande client '.($missionId ? 'validée' : 'refusée').' : '.$record->title, $missionId);
        });

        return back()->with('success', 'Décision enregistrée.');
    }
}

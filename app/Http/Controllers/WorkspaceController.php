<?php
namespace App\Http\Controllers;
use App\Actions\Concierge\WorkspaceAccess;
use App\Models\{Activity, Client, Concierge, Incident, InterventionRequest, Membership, Message, Mission, Property, ServiceTemplate, Stay};
use Illuminate\Http\{Request, RedirectResponse};
use Illuminate\Support\Facades\DB;
use Inertia\{Inertia, Response};
class WorkspaceController extends Controller {
    public function __construct(private WorkspaceAccess $access) {}
    public function index(Request $request): Response {
        if (! Membership::where('user_id', $request->user()->id)->exists()) { return Inertia::render('workspace/setup'); }
        $member = $this->access->membership($request); $tenant = $member->concierge_id;
        $properties = Property::where('concierge_id',$tenant)->with('client');
        $missions = Mission::where('concierge_id',$tenant)->with(['property.client','assignee','reports.photos','reports.author','reports.reviewer']);
        $incidents = Incident::where('concierge_id',$tenant)->with(['mission.property','author']);
        $requests = InterventionRequest::where('concierge_id',$tenant)->with(['property','author']);
        $messages = Message::where('concierge_id',$tenant)->with('author');
        if ($member->role === 'worker') {
            $missions->where('assigned_user_id',$request->user()->id);
            $properties->whereIn('id', Mission::where('concierge_id',$tenant)->where('assigned_user_id',$request->user()->id)->select('property_id'));
            $incidents->where('author_id',$request->user()->id);
        }
        if ($member->role === 'client') {
            $properties->where('client_id',$member->client_id);
            $missions->whereHas('property', fn ($q) => $q->where('client_id',$member->client_id))->whereHas('reports', fn ($q) => $q->where('status','validated'));
            $missions->with(['reports'=>fn ($q) => $q->where('status','validated')]);
            $incidents->whereNotNull('shared_at')->whereHas('mission.property',fn ($q) => $q->where('client_id',$member->client_id));
            $requests->whereHas('property',fn ($q) => $q->where('client_id',$member->client_id));
            $messages->where('client_id',$member->client_id);
        }
        $missionData = $missions->orderBy('scheduled_at')->orderBy('id')->get();
        if ($member->role === 'client') {
            $missionData->each(function (Mission $mission) { $mission->setAttribute('instructions',null); $mission->setAttribute('checklist',[]); $mission->property->setAttribute('instructions',null); });
        }
        $incidentData = $incidents->latest('id')->get();
        if ($member->role === 'client') { $incidentData->each(fn (Incident $incident) => $incident->setAttribute('description', $incident->client_message)); }
        return Inertia::render('workspace/index', [
            'concierge'=>Concierge::findOrFail($tenant), 'membership'=>$member,
            'workspaces'=>Membership::where('user_id',$request->user()->id)->with('concierge')->get(),
            'missions'=>$missionData, 'properties'=>$properties->orderBy('name')->get(),
            'clients'=>$member->role === 'manager' ? Client::where('concierge_id',$tenant)->orderBy('name')->get() : ($member->role === 'client' ? Client::where('id',$member->client_id)->get() : []),
            'members'=>$member->role === 'manager' ? Membership::where('concierge_id',$tenant)->with('user:id,name,email')->get() : [],
            'templates'=>$member->role === 'manager' ? ServiceTemplate::where('concierge_id',$tenant)->orderBy('id')->get() : [],
            'stays'=>$member->role === 'manager' ? Stay::where('concierge_id',$tenant)->with('property')->orderBy('starts_on')->get() : [],
            'incidents'=>$incidentData, 'requests'=>$member->role !== 'worker' ? $requests->latest('id')->get() : [],
            'messages'=>$member->role !== 'worker' ? $messages->orderBy('id')->get() : [],
            'activities'=>$member->role === 'manager' ? Activity::where('concierge_id',$tenant)->with('user:id,name')->latest('id')->limit(30)->get() : [],
        ]);
    }
    public function store(Request $request): RedirectResponse {
        $data = $request->validate(['name'=>['required','string','max:120']]);
        DB::transaction(function () use ($request,$data) {
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->first();
            abort_if(Membership::where('user_id',$request->user()->id)->exists(),409);
            $concierge = Concierge::create($data);
            Membership::create(['concierge_id'=>$concierge->id,'user_id'=>$request->user()->id,'role'=>'manager']);
            foreach (['Entretien et ménage'=>['Vérifier l’état général','Nettoyer les surfaces','Contrôler les équipements','Prendre les photos avant / après'], 'Visite de surveillance'=>['Vérifier les accès','Contrôler les équipements','Relever les anomalies'], 'Constat du bien'=>['Observer chaque pièce','Vérifier l’inventaire','Photographier les points de contrôle']] as $name=>$checklist) {
                ServiceTemplate::create(['concierge_id'=>$concierge->id,'name'=>$name,'checklist'=>$checklist]);
            }
        });
        return to_route('dashboard')->with('success','Votre espace est prêt. Ajoutez votre premier client.');
    }
    public function switch(Request $request): RedirectResponse {
        $data=$request->validate(['concierge_id'=>['required','integer']]);
        Membership::where('user_id',$request->user()->id)->where('concierge_id',$data['concierge_id'])->firstOrFail();
        $request->session()->put('concierge_id',$data['concierge_id']); return to_route('dashboard');
    }
}

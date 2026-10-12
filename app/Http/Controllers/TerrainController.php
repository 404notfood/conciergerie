<?php
namespace App\Http\Controllers;
use App\Actions\Concierge\WorkspaceAccess;
use App\Models\{Concierge, Mission};
use Illuminate\Http\{Request, JsonResponse};
class TerrainController extends Controller {
    public function __construct(private WorkspaceAccess $access) {}
    public function data(Request $request): JsonResponse {
        $member=$this->access->membership($request); abort_if($member->role==='client',403);
        $missions=Mission::where('concierge_id',$member->concierge_id)->where('assigned_user_id',$request->user()->id)->whereIn('status',['accepted','in_progress','changes_requested'])->with(['property','reports'])->orderBy('scheduled_at')->get();
        return response()->json([
            'identity'=>$member->concierge_id.':'.$request->user()->id, 'user'=>['id'=>$request->user()->id,'name'=>$request->user()->name], 'concierge'=>Concierge::findOrFail($member->concierge_id)->name,'csrf'=>csrf_token(),
            'missions'=>$missions->map(fn (Mission $mission)=>[
                'id'=>$mission->id,'title'=>$mission->title,'kind'=>$mission->kind,'scheduled_at'=>$mission->scheduled_at->format('Y-m-d\TH:i:s'),'version'=>$mission->version,'status'=>$mission->status,'checklist'=>$mission->checklist,'instructions'=>$mission->instructions,
                'property'=>['name'=>$mission->property->name,'address'=>$mission->property->address,'instructions'=>$mission->property->instructions,'access_code'=>$mission->property->offline_access?$mission->property->access_code:null],
                'feedback'=>$mission->reports->first()?->feedback,'report_url'=>route('missions.report',$mission->id),'incident_url'=>route('missions.incident',$mission->id),
            ]),
        ],200,['Cache-Control'=>'private, no-store']);
    }
}

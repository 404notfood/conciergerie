<?php
namespace App\Actions\Concierge;
use App\Models\{Mission, Photo, Report};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;
class SubmitReport {
    public function __construct(private WorkspaceAccess $access, private QuotaGate $quotas) {}
    public function handle(Request $request, int $missionId): Report {
        $member=$this->access->membership($request); abort_if($member->role==='client',403);
        $data=$request->validate(['operation_id'=>['required','uuid'],'version'=>['required','integer','min:1'],'notes'=>['required','string','max:10000'],'answers'=>['present','array','max:50'],'answers.*'=>['boolean'],'photos'=>['nullable','array','max:20'],'photos.*'=>['file','image','mimes:jpg,jpeg,png,webp','max:10240'],'stages'=>['nullable','array','max:20'],'stages.*'=>['required','in:before,after,observation'],'captured_at'=>['nullable','array','max:20'],'captured_at.*'=>['nullable','date']]);
        $paths=[];
        try {
            return DB::transaction(function () use ($request,$missionId,$member,$data,&$paths) {
                \App\Models\Concierge::whereKey($member->concierge_id)->lockForUpdate()->firstOrFail();
                $mission=Mission::where('concierge_id',$member->concierge_id)->lockForUpdate()->findOrFail($missionId);
                abort_unless($member->role==='manager' || $mission->assigned_user_id===$request->user()->id,404);
                $existing=Report::where('concierge_id',$member->concierge_id)->where('author_id',$request->user()->id)->where('operation_id',$data['operation_id'])->first();
                if ($existing) { abort_unless($existing->mission_id===$mission->id,409,'Identifiant déjà utilisé.'); return $existing; }
                abort_unless($mission->version===(int)$data['version'] && in_array($mission->status,['accepted','in_progress','changes_requested'],true),409,'Mission modifiée, annulée ou réaffectée : conservez les preuves et contactez le responsable.');
                if (count($data['answers'])!==count($mission->checklist) || in_array(false,array_map(fn ($value)=>(bool)$value,$data['answers']),true)) { throw ValidationException::withMessages(['answers'=>'Validez tous les points de la checklist avant la soumission.']); }
                $this->quotas->storage($request,array_sum(array_map(fn($file)=>$file->getSize(),$request->file('photos',[]))));
                $report=Report::create(['concierge_id'=>$member->concierge_id,'mission_id'=>$mission->id,'author_id'=>$request->user()->id,'operation_id'=>$data['operation_id'],'notes'=>$data['notes'],'answers'=>array_values($data['answers'])]);
                foreach ($request->file('photos',[]) as $index=>$file) {
                    $path=$file->store('concierges/'.$member->concierge_id.'/reports','local');
                    if (!$path) { throw new \RuntimeException('Enregistrement photo impossible.'); }
                    $paths[]=$path;
                    Photo::create(['concierge_id'=>$member->concierge_id,'report_id'=>$report->id,'author_id'=>$request->user()->id,'path'=>$path,'size_bytes'=>$file->getSize(),'stage'=>$data['stages'][$index]??'observation','captured_at'=>$data['captured_at'][$index]??null]);
                }
                $mission->update(['status'=>'submitted','version'=>$mission->version+1]);
                $this->access->log($request,'Compte rendu soumis : '.$mission->title,$mission->id);
                return $report;
            });
        } catch (\Throwable $error) { Storage::disk('local')->delete($paths); throw $error; }
    }
}

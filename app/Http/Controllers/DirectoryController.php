<?php
namespace App\Http\Controllers;
use App\Actions\Concierge\{WorkspaceAccess, ImportCalendar};
use App\Models\{Client, Membership, Property, ServiceTemplate, Stay, User};
use Illuminate\Http\{Request, RedirectResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class DirectoryController extends Controller {
    public function __construct(private WorkspaceAccess $access) {}
    public function client(Request $request): RedirectResponse {
        $member=$this->access->manager($request);
        $data=$request->validate(['name'=>['required','string','max:120'],'email'=>['required','email','max:255',Rule::unique('clients')->where('concierge_id',$member->concierge_id)],'phone'=>['nullable','string','max:40'],'kind'=>['required',Rule::in(['individual','company'])]]);
        Client::create([...$data,'concierge_id'=>$member->concierge_id]); return back()->with('success','Client ajouté.');
    }
    public function property(Request $request): RedirectResponse {
        $member=$this->access->manager($request);
        $data=$request->validate(['client_id'=>['required',Rule::exists('clients','id')->where('concierge_id',$member->concierge_id)],'name'=>['required','string','max:120'],'address'=>['required','string','max:255'],'kind'=>['required',Rule::in(['rental','residence','business','other'])],'instructions'=>['nullable','string','max:5000'],'access_code'=>['nullable','string','max:500'],'offline_access'=>['boolean']]);
        Property::create([...$data,'concierge_id'=>$member->concierge_id]); return back()->with('success','Lieu ajouté.');
    }
    public function member(Request $request): RedirectResponse {
        $member=$this->access->manager($request);
        $data=$request->validate(['name'=>['required','string','max:120'],'email'=>['required','email','max:255','unique:users,email'],'password'=>['required','string','min:12','confirmed'],'role'=>['required',Rule::in(['manager','worker','client'])],'client_id'=>['nullable','required_if:role,client',Rule::exists('clients','id')->where('concierge_id',$member->concierge_id)],'team'=>['nullable','string','max:120'],'company'=>['nullable','string','max:120']]);
        DB::transaction(function () use ($data,$member) {
            $user=User::create(['name'=>$data['name'],'email'=>$data['email'],'password'=>$data['password']]);
            $user->forceFill(['email_verified_at'=>now()])->save();
            Membership::create(['concierge_id'=>$member->concierge_id,'user_id'=>$user->id,'role'=>$data['role'],'client_id'=>$data['role']==='client'?$data['client_id']:null,'team'=>$data['team']??null,'company'=>$data['company']??null]);
        }); return back()->with('success','Compte individuel créé. Communiquez les identifiants à cette personne.');
    }
    public function template(Request $request): RedirectResponse {
        $member=$this->access->manager($request);
        $data=$request->validate(['name'=>['required','string','max:120'],'checklist'=>['required','array','min:1','max:50'],'checklist.*'=>['required','string','max:255']]);
        ServiceTemplate::create([...$data,'concierge_id'=>$member->concierge_id]); return back()->with('success','Modèle de prestation ajouté.');
    }
    public function stay(Request $request): RedirectResponse {
        $member=$this->access->manager($request);
        $data=$request->validate(['property_id'=>['required',Rule::exists('properties','id')->where('concierge_id',$member->concierge_id)],'title'=>['required','string','max:120'],'starts_on'=>['required','date'],'ends_on'=>['required','date','after:starts_on'],'kind'=>['required',Rule::in(['stay','blocked'])]]);
        Stay::create([...$data,'concierge_id'=>$member->concierge_id]); return back()->with('success','Période ajoutée au planning.');
    }
    public function import(Request $request, ImportCalendar $import): RedirectResponse {
        $member=$this->access->manager($request);
        $data=$request->validate(['property_id'=>['required',Rule::exists('properties','id')->where('concierge_id',$member->concierge_id)],'source'=>['required',Rule::in(['airbnb','booking','ical'])],'calendar'=>['required','file','max:2048']]);
        $count=$import->handle($member->concierge_id,(int)$data['property_id'],$data['source'],$request->file('calendar')->get());
        return back()->with('success', $count.' périodes importées / actualisées. Vérifiez leur nature avant de planifier une mission.');
    }
}

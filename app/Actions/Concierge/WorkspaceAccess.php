<?php
namespace App\Actions\Concierge;
use App\Models\{Activity, Membership, Mission};
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
class WorkspaceAccess {
    public function membership(Request $request): Membership {
        $query = Membership::where('user_id', $request->user()->id);
        $selected = $request->session()->get('concierge_id');
        $member = $selected ? (clone $query)->where('concierge_id', $selected)->first() : null;
        return $member ?? $query->orderBy('id')->firstOrFail();
    }
    public function manager(Request $request): Membership {
        $member = $this->membership($request); abort_unless($member->role === 'manager', 403); return $member;
    }
    public function record(Request $request, string $model, int $id): Model {
        return $model::where('concierge_id', $this->membership($request)->concierge_id)->findOrFail($id);
    }
    public function mission(Request $request, int $id): Mission {
        $member = $this->membership($request);
        $mission = Mission::where('concierge_id', $member->concierge_id)->with('property')->findOrFail($id);
        if ($member->role === 'worker') { abort_unless($mission->assigned_user_id === $request->user()->id, 404); }
        if ($member->role === 'client') { abort_unless($mission->property->client_id === $member->client_id && $mission->reports()->where('status','validated')->exists(), 404); }
        return $mission;
    }
    public function log(Request $request, string $description, ?int $missionId = null): void {
        Activity::create(['concierge_id'=>$this->membership($request)->concierge_id, 'user_id'=>$request->user()->id, 'description'=>$description, 'mission_id'=>$missionId]);
    }
}

<?php

namespace App\Actions\Concierge;

use App\Models\Activity;
use App\Models\Concierge;
use App\Models\Membership;
use App\Models\Mission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class WorkspaceAccess
{
    public function membership(Request $request): Membership
    {
        if ($request->user()->is_admin && $request->session()->has('admin_concierge_id')) {
            $concierge = Concierge::whereKey($request->session()->get('admin_concierge_id'))->firstOrFail();

            return new Membership(['concierge_id' => $concierge->id, 'user_id' => $request->user()->id, 'role' => 'manager']);
        }
        $query = Membership::where('user_id', $request->user()->id);
        $selected = $request->session()->get('concierge_id');
        $member = $selected ? (clone $query)->where('concierge_id', $selected)->first() : null;

        return $member ?? $query->orderBy('id')->firstOrFail();
    }

    public function manager(Request $request): Membership
    {
        $member = $this->membership($request);
        abort_unless($member->role === 'manager', 403);

        return $member;
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    public function record(Request $request, string $model, int $id): Model
    {
        return (new $model)->newQuery()->where('concierge_id', $this->membership($request)->concierge_id)->whereKey($id)->firstOrFail();
    }

    public function mission(Request $request, int $id): Mission
    {
        $member = $this->membership($request);
        $mission = Mission::where('concierge_id', $member->concierge_id)->with('property')->findOrFail($id);
        if ($member->role === 'coordinator') {
            abort_unless($mission->assigned_company === $member->company, 404);
        }
        if ($member->role === 'worker') {
            abort_unless($mission->assigned_user_id === $request->user()->id, 404);
        }
        if ($member->role === 'client') {
            abort_unless($mission->property->client_id === $member->client_id && $mission->reports()->where('status', 'validated')->exists(), 404);
        }

        return $mission;
    }

    public function log(Request $request, string $description, ?int $missionId = null): void
    {
        Activity::create(['concierge_id' => $this->membership($request)->concierge_id, 'user_id' => $request->user()->id, 'description' => $description, 'mission_id' => $missionId]);
    }
}

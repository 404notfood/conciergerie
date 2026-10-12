<?php

namespace App\Http\Controllers;

use App\Actions\Concierge\WorkspaceAccess;
use App\Models\Concierge;
use App\Models\Membership;
use App\Models\StaffAvailability;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AvailabilityController extends Controller
{
    public function __construct(private WorkspaceAccess $access) {}

    public function store(Request $request): RedirectResponse
    {
        $member = $this->access->membership($request);
        abort_unless(in_array($member->role, ['manager', 'worker', 'coordinator'], true), 403);
        $data = $request->validate(['user_id' => ['required', 'integer', Rule::exists('memberships', 'user_id')->where('concierge_id', $member->concierge_id)->whereIn('role', ['manager', 'worker'])], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'note' => ['nullable', 'string', 'max:255']]);
        if ($member->role === 'worker') {
            abort_unless((int) $data['user_id'] === $request->user()->id, 403);
        }
        if ($member->role === 'coordinator') {
            Membership::where('concierge_id', $member->concierge_id)->where('company', $member->company)->where('role', 'worker')->where('user_id', $data['user_id'])->firstOrFail();
        }
        $data['starts_at'] = Carbon::parse($data['starts_at'], 'Europe/Paris')->utc();
        $data['ends_at'] = Carbon::parse($data['ends_at'], 'Europe/Paris')->utc();
        DB::transaction(function () use ($request, $member, $data) {
            Concierge::whereKey($member->concierge_id)->lockForUpdate()->firstOrFail();
            User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            StaffAvailability::create([...$data, 'concierge_id' => $member->concierge_id]);
            $this->access->log($request, 'Indisponibilité enregistrée');
        });

        return back()->with('success', 'Indisponibilité enregistrée. Les missions déjà prévues restent visibles et doivent être réorganisées si nécessaire.');
    }

    public function destroy(Request $request, int $availability): RedirectResponse
    {
        $member = $this->access->membership($request);
        $record = StaffAvailability::where('concierge_id', $member->concierge_id)->findOrFail($availability);
        abort_unless($member->role === 'manager' || ($member->role === 'worker' && $record->user_id === $request->user()->id) || ($member->role === 'coordinator' && Membership::where('concierge_id', $member->concierge_id)->where('company', $member->company)->where('role', 'worker')->where('user_id', $record->user_id)->exists()), 403);
        $record->delete();
        $this->access->log($request, 'Indisponibilité retirée');

        return back()->with('success', 'Indisponibilité retirée.');
    }
}

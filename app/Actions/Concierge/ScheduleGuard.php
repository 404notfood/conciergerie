<?php

namespace App\Actions\Concierge;

use App\Models\Membership;
use App\Models\Mission;
use App\Models\StaffAvailability;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ScheduleGuard
{
    public function check(int $tenant, ?int $userId, Carbon $start, int $duration, ?int $exceptMission = null): void
    {
        if (! $userId) {
            return;
        }
        $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
        if ($user->disabled_at || ! Membership::where('concierge_id', $tenant)->where('user_id', $userId)->whereIn('role', ['manager', 'worker'])->exists()) {
            throw ValidationException::withMessages(['assigned_user_id' => 'Cette personne ne peut pas recevoir de mission dans cet espace.']);
        }
        $end = $start->copy()->addMinutes($duration);
        $absence = StaffAvailability::where('user_id', $userId)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
        $overlap = Mission::where('assigned_user_id', $userId)->whereNotIn('status', ['validated', 'cancelled'])->when($exceptMission, fn ($query) => $query->where('id', '!=', $exceptMission))->where('scheduled_at', '<', $end)->where('scheduled_at', '>', $start->copy()->subDay())->get()->contains(fn (Mission $mission) => $mission->scheduled_at->copy()->addMinutes($mission->duration)->gt($start));
        if ($absence || $overlap) {
            throw ValidationException::withMessages(['assigned_user_id' => $absence ? 'Cette personne est indisponible sur ce créneau.' : 'Cette personne a déjà une mission sur ce créneau.']);
        }
    }
}

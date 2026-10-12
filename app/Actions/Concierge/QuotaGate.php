<?php

namespace App\Actions\Concierge;

use App\Models\Concierge;
use App\Models\Membership;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotaGate
{
    public function __construct(private WorkspaceAccess $access, private SubscriptionManager $subscriptions) {}

    public function feature(Request $request, string $feature): void
    {
        if ($request->user()->is_admin) {
            return;
        }
        $subscription = $this->subscriptions->ensure($this->access->membership($request)->concierge_id);
        abort_unless($this->subscriptions->usable($subscription), 402, 'Votre abonnement ne permet plus cette action.');
        if (! ($this->subscriptions->features($subscription)[$feature] ?? false)) {
            throw ValidationException::withMessages(['subscription' => 'L’option « '.SubscriptionManager::FEATURES[$feature].' » n’est pas incluse dans votre abonnement.']);
        }
    }

    /**
     * @template TResult
     *
     * @param  callable():TResult  $callback
     * @param  array<string,int>  $increments
     * @return TResult
     */
    public function mutate(Request $request, array $increments, callable $callback, ?string $feature = null): mixed
    {
        return DB::transaction(function () use ($request, $increments, $callback, $feature) {
            $tenant = $this->access->membership($request)->concierge_id;
            Concierge::whereKey($tenant)->lockForUpdate()->firstOrFail();
            if (! $request->user()->is_admin) {
                $subscription = $this->subscriptions->ensure($tenant);
                abort_unless($this->subscriptions->usable($subscription), 402, 'Abonnement inactif.');
                if ($feature) {
                    $this->feature($request, $feature);
                }
                $limits = $this->subscriptions->limits($subscription);
                $usage = $this->subscriptions->usage($tenant);
                foreach ($increments as $resource => $amount) {
                    if (($limits[$resource] ?? null) !== null && $limits[$resource] < $usage[$resource] + $amount) {
                        throw ValidationException::withMessages(['subscription' => 'Limite atteinte : '.SubscriptionManager::LIMITS[$resource].' ('.$limits[$resource].'). Contactez l’administrateur pour adapter votre offre.']);
                    }
                }
            }

            return $callback();
        });
    }

    public function team(Request $request, ?string $team, string $role): void
    {
        if ($role === 'client' || $request->user()->is_admin) {
            return;
        }
        $tenant = $this->access->membership($request)->concierge_id;
        $name = $team ?: 'Équipe principale';
        $exists = Membership::where('concierge_id', $tenant)->where('role', '!=', 'client')->get()->contains(fn ($member) => ($member->team ?: 'Équipe principale') === $name);
        $limit = $this->subscriptions->limits($this->subscriptions->ensure($tenant))['teams'] ?? null;
        if (! $exists && $limit !== null && $this->subscriptions->usage($tenant)['teams'] >= $limit) {
            throw ValidationException::withMessages(['team' => 'La limite d’agences / équipes de votre abonnement est atteinte.']);
        }
    }

    public function storage(Request $request, int $incomingBytes): void
    {
        if ($request->user()->is_admin) {
            return;
        }
        $tenant = $this->access->membership($request)->concierge_id;
        $subscription = $this->subscriptions->ensure($tenant);
        $limit = $this->subscriptions->limits($subscription)['storage_mb'] ?? null;
        if ($limit !== null && Photo::where('concierge_id', $tenant)->sum('size_bytes') + $incomingBytes > $limit * 1048576) {
            throw ValidationException::withMessages(['photos' => 'Le quota de stockage photo est atteint. Les preuves locales doivent être conservées.']);
        }
    }
}

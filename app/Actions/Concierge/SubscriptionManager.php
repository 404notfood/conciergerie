<?php

namespace App\Actions\Concierge;

use App\Models\ApplicationSetting;
use App\Models\Client;
use App\Models\Concierge;
use App\Models\ConciergeSubscription;
use App\Models\Membership;
use App\Models\Mission;
use App\Models\Photo;
use App\Models\PlanAddon;
use App\Models\Property;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

class SubscriptionManager
{
    public const LIMITS = ['staff' => 'Personnel (responsables et intervenants)', 'clients' => 'Clients', 'properties' => 'Lieux d’intervention', 'teams' => 'Agences / équipes', 'missions_month' => 'Missions créées par mois', 'storage_mb' => 'Stockage photo (Mo)'];

    public const FEATURES = ['offline' => 'Travail terrain hors connexion', 'calendar_import' => 'Import de calendriers iCal', 'client_portal' => 'Espace client', 'messaging' => 'Messagerie client', 'custom_templates' => 'Prestations personnalisées', 'subcontracting' => 'Sous-traitance'];

    public function settings(): ApplicationSetting
    {
        $settings = ApplicationSetting::query()->first();
        if ($settings) {
            return $settings;
        }
        $plan = SubscriptionPlan::firstOrCreate(['name' => 'Essai — configuration initiale'], ['monthly_price_cents' => 0, 'limits' => ['staff' => 5, 'clients' => 20, 'properties' => 30, 'teams' => 3, 'missions_month' => 200, 'storage_mb' => 2048], 'features' => array_fill_keys(array_keys(self::FEATURES), true), 'is_active' => true, 'is_public' => false, 'description' => 'Paramètres initiaux modifiables par l’administrateur. Aucun tarif commercial publié.']);

        return ApplicationSetting::firstOrCreate(['id' => 1], ['default_plan_id' => $plan->id, 'trial_days' => 14, 'registration_open' => true]);
    }

    public function ensure(int $conciergeId): ConciergeSubscription
    {
        $existing = ConciergeSubscription::where('concierge_id', $conciergeId)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($conciergeId) {
            Concierge::whereKey($conciergeId)->lockForUpdate()->firstOrFail();
            $existing = ConciergeSubscription::where('concierge_id', $conciergeId)->first();
            if ($existing) {
                return $existing;
            }
            $settings = $this->settings();
            $plan = SubscriptionPlan::whereKey($settings->default_plan_id)->firstOrFail();

            return ConciergeSubscription::create(['concierge_id' => $conciergeId, 'subscription_plan_id' => $plan->id, 'status' => 'trialing', 'terms' => $this->terms($plan, []), 'starts_on' => now()->toDateString(), 'expires_at' => now()->addDays($settings->trial_days)]);
        });
    }

    /**
     * @param  array<int,PlanAddon>  $addons
     * @return array<string,mixed>
     */
    public function terms(SubscriptionPlan $plan, array $addons): array
    {
        $limits = $plan->limits;
        $features = $plan->features;
        $total = $plan->monthly_price_cents;
        $selected = [];
        foreach ($addons as $addon) {
            $total += $addon->monthly_price_cents;
            foreach ($addon->limit_bonus as $name => $value) {
                if (($limits[$name] ?? null) !== null) {
                    $limits[$name] += $value;
                }
            }
            foreach ($addon->features as $name => $enabled) {
                if ($enabled) {
                    $features[$name] = true;
                }
            }
            $selected[] = ['id' => $addon->id, 'name' => $addon->name, 'monthly_price_cents' => $addon->monthly_price_cents, 'limit_bonus' => $addon->limit_bonus, 'features' => $addon->features];
        }

        return ['plan_name' => $plan->name, 'base_price_cents' => $plan->monthly_price_cents, 'monthly_total_cents' => $total, 'limits' => $limits, 'features' => $features, 'addons' => $selected];
    }

    public function usable(ConciergeSubscription $subscription): bool
    {
        return in_array($subscription->status, ['trialing', 'active'], true) && ($subscription->expires_at === null || $subscription->expires_at->isFuture());
    }

    /** @return array<string,int|null> */
    public function limits(ConciergeSubscription $subscription): array
    {
        return array_replace($subscription->terms['limits'], $subscription->limit_overrides ?? []);
    }

    /** @return array<string,bool> */
    public function features(ConciergeSubscription $subscription): array
    {
        return array_replace($subscription->terms['features'], $subscription->feature_overrides ?? []);
    }

    public function price(ConciergeSubscription $subscription): int
    {
        return $subscription->price_override_cents ?? $subscription->terms['monthly_total_cents'];
    }

    /** @return array<string,int> */
    public function usage(int $conciergeId): array
    {
        $members = Membership::where('concierge_id', $conciergeId)->where('role', '!=', 'client');

        return ['staff' => (clone $members)->count(), 'clients' => Client::where('concierge_id', $conciergeId)->count(), 'properties' => Property::where('concierge_id', $conciergeId)->count(), 'teams' => (clone $members)->get(['team'])->map(fn ($member) => $member->team ?: 'Équipe principale')->unique()->count(), 'missions_month' => Mission::where('concierge_id', $conciergeId)->where('created_at', '>=', now()->startOfMonth())->where('created_at', '<', now()->startOfMonth()->addMonth())->count(), 'storage_mb' => (int) ceil(Photo::where('concierge_id', $conciergeId)->sum('size_bytes') / 1048576)];
    }

    public function cents(string $amount): int
    {
        $parts = explode('.', $amount);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }
}

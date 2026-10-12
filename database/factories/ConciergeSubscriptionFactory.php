<?php

namespace Database\Factories;

use App\Actions\Concierge\SubscriptionManager;
use App\Models\Concierge;
use App\Models\ConciergeSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConciergeSubscription> */
class ConciergeSubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return ['concierge_id' => Concierge::factory(), 'subscription_plan_id' => SubscriptionPlan::factory(), 'status' => 'trialing', 'terms' => fn (array $a) => ['plan_name' => SubscriptionPlan::whereKey($a['subscription_plan_id'])->firstOrFail()->name, 'base_price_cents' => 2900, 'monthly_total_cents' => 2900, 'limits' => ['staff' => 5, 'clients' => 20, 'properties' => 30, 'teams' => 3, 'missions_month' => 200, 'storage_mb' => 1024], 'features' => array_fill_keys(array_keys(SubscriptionManager::FEATURES), true), 'addons' => []], 'starts_on' => now()->toDateString(), 'expires_at' => now()->addDays(14)];
    }
}

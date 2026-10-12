<?php

namespace Database\Factories;

use App\Actions\Concierge\SubscriptionManager;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SubscriptionPlan> */
class SubscriptionPlanFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->word(), 'monthly_price_cents' => 2900, 'limits' => ['staff' => 5, 'clients' => 20, 'properties' => 30, 'teams' => 3, 'missions_month' => 200, 'storage_mb' => 1024], 'features' => array_fill_keys(array_keys(SubscriptionManager::FEATURES), true), 'is_active' => true, 'is_public' => false];
    }
}

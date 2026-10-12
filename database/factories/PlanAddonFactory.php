<?php

namespace Database\Factories;

use App\Actions\Concierge\SubscriptionManager;
use App\Models\PlanAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlanAddon> */
class PlanAddonFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->word(), 'monthly_price_cents' => 900, 'limit_bonus' => array_fill_keys(array_keys(SubscriptionManager::LIMITS), 0), 'features' => array_fill_keys(array_keys(SubscriptionManager::FEATURES), false), 'is_active' => true];
    }
}

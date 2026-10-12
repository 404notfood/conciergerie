<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Concierge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Activity> */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        return ['concierge_id' => Concierge::factory(), 'user_id' => User::factory(), 'description' => 'Mission planifiée.'];
    }
}

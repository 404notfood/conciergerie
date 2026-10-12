<?php

namespace Database\Factories;

use App\Models\Concierge;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Concierge> */
class ConciergeFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->company()];
    }
}

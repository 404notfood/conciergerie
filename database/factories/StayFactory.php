<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\Stay;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Stay> */
class StayFactory extends Factory
{
    public function definition(): array
    {
        return ['property_id' => Property::factory(), 'concierge_id' => fn (array $attributes) => Property::whereKey($attributes['property_id'])->firstOrFail()->concierge_id, 'title' => 'Séjour', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-16', 'source' => 'manual', 'kind' => 'stay'];
    }
}

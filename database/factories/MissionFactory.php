<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Mission> */
class MissionFactory extends Factory
{
    public function definition(): array
    {
        return ['property_id' => Property::factory(), 'concierge_id' => fn (array $attributes) => Property::whereKey($attributes['property_id'])->firstOrFail()->concierge_id, 'title' => 'Visite de surveillance', 'scheduled_at' => '2026-10-12 09:00:00', 'checklist' => ['Vérifier les équipements', 'Prendre les preuves photo'], 'status' => 'planned', 'version' => 1, 'duration' => 60];
    }
}

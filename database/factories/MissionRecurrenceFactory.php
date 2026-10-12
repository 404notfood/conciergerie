<?php

namespace Database\Factories;

use App\Models\MissionRecurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MissionRecurrence> */
class MissionRecurrenceFactory extends Factory
{
    public function definition(): array
    {
        return ['concierge_id' => \App\Models\Concierge::factory(), 'property_id' => fn (array $attributes) => \App\Models\Property::factory()->create(['concierge_id' => $attributes['concierge_id'], 'client_id' => \App\Models\Client::factory()->create(['concierge_id' => $attributes['concierge_id']])->id])->id, 'template_id' => fn (array $attributes) => \App\Models\ServiceTemplate::factory()->create(['concierge_id' => $attributes['concierge_id']])->id, 'assigned_user_id' => null, 'title' => 'Visite périodique', 'kind' => 'service', 'frequency' => 'weekly', 'interval' => 1, 'starts_on' => now('Europe/Paris')->toDateString(), 'next_on' => fn (array $attributes) => $attributes['starts_on'], 'time' => '09:00', 'duration' => 60, 'is_active' => true];
    }
}

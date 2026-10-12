<?php

namespace Database\Factories;

use App\Models\IncidentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IncidentEvent> */
class IncidentEventFactory extends Factory
{
    public function definition(): array
    {
        return ['incident_id' => \App\Models\Incident::factory(), 'concierge_id' => fn (array $attributes) => \App\Models\Incident::findOrFail($attributes['incident_id'])->concierge_id, 'user_id' => \App\Models\User::factory(), 'action' => 'created', 'description' => 'Incident signalé.'];
    }
}

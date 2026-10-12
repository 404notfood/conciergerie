<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IncidentEvent> */
class IncidentEventFactory extends Factory
{
    public function definition(): array
    {
        return ['incident_id' => Incident::factory(), 'concierge_id' => fn (array $attributes) => Incident::whereKey($attributes['incident_id'])->firstOrFail()->concierge_id, 'user_id' => User::factory(), 'action' => 'created', 'description' => 'Incident signalé.'];
    }
}

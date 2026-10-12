<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Incident> */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        return ['mission_id' => Mission::factory(), 'concierge_id' => fn (array $attributes) => Mission::whereKey($attributes['mission_id'])->firstOrFail()->concierge_id, 'author_id' => User::factory(), 'operation_id' => (string) Str::uuid(), 'description' => 'Équipement endommagé.'];
    }
}

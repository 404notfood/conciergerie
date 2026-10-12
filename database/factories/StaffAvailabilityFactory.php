<?php

namespace Database\Factories;

use App\Models\StaffAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffAvailability> */
class StaffAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        return ['concierge_id' => \App\Models\Concierge::factory(), 'user_id' => \App\Models\User::factory(), 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'note' => 'Indisponible'];
    }
}

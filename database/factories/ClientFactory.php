<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Concierge;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return ['concierge_id' => Concierge::factory(), 'name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'kind' => 'individual'];
    }
}

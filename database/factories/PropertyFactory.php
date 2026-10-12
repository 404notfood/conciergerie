<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return ['client_id' => Client::factory(), 'concierge_id' => fn (array $attributes) => Client::whereKey($attributes['client_id'])->firstOrFail()->concierge_id, 'name' => 'Résidence du Lac', 'address' => '12 rue du Lac, Annecy', 'kind' => 'residence', 'instructions' => 'Vérifier les équipements.'];
    }
}

<?php

namespace Database\Factories;

use App\Models\InterventionRequest;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InterventionRequest> */
class InterventionRequestFactory extends Factory
{
    public function definition(): array
    {
        return ['property_id' => Property::factory(), 'concierge_id' => fn (array $attributes) => Property::whereKey($attributes['property_id'])->firstOrFail()->concierge_id, 'author_id' => User::factory(), 'title' => 'Demande de contrôle', 'description' => 'Merci de contrôler les équipements.'];
    }
}

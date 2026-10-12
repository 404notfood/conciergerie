<?php

namespace Database\Factories;

use App\Models\Concierge;
use App\Models\ServiceTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceTemplate> */
class ServiceTemplateFactory extends Factory
{
    public function definition(): array
    {
        return ['concierge_id' => Concierge::factory(), 'name' => 'Entretien', 'checklist' => ['Vérifier les équipements', 'Prendre les preuves photo']];
    }
}

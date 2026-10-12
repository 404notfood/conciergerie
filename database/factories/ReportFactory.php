<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Report> */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return ['mission_id' => Mission::factory(), 'concierge_id' => fn (array $attributes) => Mission::whereKey($attributes['mission_id'])->firstOrFail()->concierge_id, 'author_id' => User::factory(), 'operation_id' => (string) Str::uuid(), 'notes' => 'Contrôle effectué.', 'answers' => [true, true], 'status' => 'submitted'];
    }
}

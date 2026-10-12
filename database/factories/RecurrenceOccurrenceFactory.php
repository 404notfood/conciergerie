<?php

namespace Database\Factories;

use App\Models\RecurrenceOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecurrenceOccurrence> */
class RecurrenceOccurrenceFactory extends Factory
{
    public function definition(): array
    {
        return ['mission_recurrence_id' => \App\Models\MissionRecurrence::factory(), 'concierge_id' => fn (array $attributes) => \App\Models\MissionRecurrence::findOrFail($attributes['mission_recurrence_id'])->concierge_id, 'scheduled_on' => now('Europe/Paris')->toDateString(), 'status' => 'pending', 'snapshot' => fn (array $attributes) => (function () use ($attributes) { $rule = \App\Models\MissionRecurrence::findOrFail($attributes['mission_recurrence_id']); return ['property_id' => $rule->property_id, 'assigned_user_id' => $rule->assigned_user_id, 'title' => $rule->title, 'kind' => $rule->kind, 'scheduled_at' => now()->addDay()->toIso8601String(), 'duration' => 60, 'instructions' => null, 'checklist' => $rule->template->checklist]; })()];
    }
}

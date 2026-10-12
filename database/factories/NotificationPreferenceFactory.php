<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationPreference> */
class NotificationPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return ['concierge_id' => \App\Models\Concierge::factory(), 'user_id' => \App\Models\User::factory(), 'email_events' => [], 'browser_events' => []];
    }
}

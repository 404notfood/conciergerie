<?php

namespace Database\Factories;

use App\Models\BusinessNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessNotification> */
class BusinessNotificationFactory extends Factory
{
    public function definition(): array
    {
        return ['concierge_id' => \App\Models\Concierge::factory(), 'user_id' => \App\Models\User::factory(), 'event' => 'mission_assigned', 'title' => 'Mission attribuée', 'body' => 'Une mission attend votre consultation.', 'url' => '/dashboard?view=missions', 'dedupe_key' => hash('sha256', fake()->uuid())];
    }
}

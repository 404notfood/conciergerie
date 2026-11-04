<?php

namespace Database\Factories;

use App\Models\CalendarFeed;
use App\Models\Client;
use App\Models\Concierge;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CalendarFeed> */
class CalendarFeedFactory extends Factory
{
    public function definition(): array
    {
        return ['concierge_id' => Concierge::factory(), 'property_id' => fn (array $attributes) => Property::factory()->create(['concierge_id' => $attributes['concierge_id'], 'client_id' => Client::factory()->create(['concierge_id' => $attributes['concierge_id']])->id])->id, 'name' => 'Calendrier Airbnb', 'source' => 'airbnb', 'url' => 'https://calendar.example.com/reservations.ics', 'is_active' => true];
    }
}

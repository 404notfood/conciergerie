<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Message> */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return ['client_id' => Client::factory(), 'concierge_id' => fn (array $attributes) => Client::whereKey($attributes['client_id'])->firstOrFail()->concierge_id, 'author_id' => User::factory(), 'body' => 'Bonjour, voici votre suivi.'];
    }
}

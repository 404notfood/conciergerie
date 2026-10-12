<?php

namespace Database\Factories;

use App\Models\Concierge;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Membership> */
class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return ['concierge_id' => Concierge::factory(), 'user_id' => User::factory(), 'role' => 'manager'];
    }
}

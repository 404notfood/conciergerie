<?php

namespace Database\Factories;

use App\Models\Photo;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Photo> */
class PhotoFactory extends Factory
{
    public function definition(): array
    {
        return ['report_id' => Report::factory(), 'concierge_id' => fn (array $attributes) => Report::whereKey($attributes['report_id'])->firstOrFail()->concierge_id, 'author_id' => fn (array $attributes) => Report::whereKey($attributes['report_id'])->firstOrFail()->author_id, 'path' => 'proofs/example.jpg', 'stage' => 'observation'];
    }
}

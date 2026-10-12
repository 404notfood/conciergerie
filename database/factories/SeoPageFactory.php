<?php

namespace Database\Factories;

use App\Models\SeoPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SeoPage> */
class SeoPageFactory extends Factory
{
    public function definition(): array
    {
        return ['path' => '/guides/'.fake()->unique()->slug(), 'kind' => 'content', 'title' => fake()->sentence(), 'description' => fake()->paragraph(), 'heading' => fake()->sentence(), 'intro' => fake()->paragraph(), 'sections' => [['heading' => 'Une organisation claire', 'text' => fake()->paragraph()]], 'faq' => [], 'status' => 'published', 'noindex' => false, 'published_at' => now()];
    }
}

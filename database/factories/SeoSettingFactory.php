<?php

namespace Database\Factories;

use App\Models\SeoSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SeoSetting> */
class SeoSettingFactory extends Factory
{
    public function definition(): array
    {
        return ['public_url' => 'https://example.com', 'indexing_enabled' => true, 'allow_ai_search' => true, 'allow_ai_training' => false];
    }
}

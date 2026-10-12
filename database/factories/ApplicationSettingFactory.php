<?php

namespace Database\Factories;

use App\Models\ApplicationSetting;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApplicationSetting> */
class ApplicationSettingFactory extends Factory
{
    public function definition(): array
    {
        return ['default_plan_id' => SubscriptionPlan::factory(), 'trial_days' => 14, 'registration_open' => true];
    }
}

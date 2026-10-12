<?php

namespace App\Models;

use Database\Factories\ApplicationSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $default_plan_id
 * @property int $trial_days
 * @property bool $registration_open
 */
#[Fillable(['default_plan_id', 'trial_days', 'registration_open'])]
class ApplicationSetting extends Model
{
    /** @use HasFactory<ApplicationSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['trial_days' => 'integer', 'registration_open' => 'boolean'];
    }
}

<?php

namespace App\Models;

use Database\Factories\SeoSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property ?string $site_name
 * @property ?string $public_url
 * @property bool $indexing_enabled
 * @property bool $allow_ai_search
 * @property bool $allow_ai_training
 * @property ?string $google_verification
 * @property ?string $bing_verification
 * @property ?string $social_image
 * @property ?string $contact_email
 */
#[Fillable(['site_name', 'public_url', 'indexing_enabled', 'allow_ai_search', 'allow_ai_training', 'google_verification', 'bing_verification', 'social_image', 'contact_email'])]
class SeoSetting extends Model
{
    /** @use HasFactory<SeoSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['indexing_enabled' => 'boolean', 'allow_ai_search' => 'boolean', 'allow_ai_training' => 'boolean'];
    }
}

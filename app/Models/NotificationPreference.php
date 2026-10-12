<?php

namespace App\Models;

use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $concierge_id
 * @property int $user_id
 * @property list<string> $email_events
 * @property list<string> $browser_events
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['concierge_id', 'user_id', 'email_events', 'browser_events'])]
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['email_events' => 'array', 'browser_events' => 'array'];
    }
}

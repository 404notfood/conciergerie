<?php

namespace App\Models;

use Database\Factories\BusinessNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $concierge_id
 * @property int $user_id
 * @property string $event
 * @property string $title
 * @property string $body
 * @property string $url
 * @property string $dedupe_key
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['concierge_id', 'user_id', 'event', 'title', 'body', 'url', 'dedupe_key', 'read_at'])]
class BusinessNotification extends Model
{
    /** @use HasFactory<BusinessNotificationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}

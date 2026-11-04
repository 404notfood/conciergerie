<?php

namespace App\Models;

use Database\Factories\CalendarFeedFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $concierge_id
 * @property int $property_id
 * @property string $name
 * @property string $source
 * @property string $url
 * @property bool $is_active
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 * @property int $event_count
 * @property Property $property
 */
#[Fillable(['concierge_id', 'property_id', 'name', 'source', 'url', 'is_active'])]
#[Hidden(['url', 'url_hash'])]
class CalendarFeed extends Model
{
    /** @use HasFactory<CalendarFeedFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $feed): void {
            $feed->setAttribute('url_hash', hash('sha256', $feed->url));
        });
    }

    protected function casts(): array
    {
        return ['url' => 'encrypted', 'is_active' => 'boolean', 'last_attempt_at' => 'datetime', 'last_synced_at' => 'datetime', 'event_count' => 'integer'];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}

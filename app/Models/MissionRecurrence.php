<?php

namespace App\Models;

use Database\Factories\MissionRecurrenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $concierge_id
 * @property int $property_id
 * @property int $template_id
 * @property int|null $assigned_user_id
 * @property string $title
 * @property string $kind
 * @property string $frequency
 * @property int $interval
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon $next_on
 * @property string $time
 * @property int $duration
 * @property string|null $instructions
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Property $property
 * @property ServiceTemplate $template
 * @property User|null $assignee
 */
#[Fillable(['concierge_id', 'property_id', 'template_id', 'assigned_user_id', 'title', 'kind', 'frequency', 'interval', 'starts_on', 'ends_on', 'next_on', 'time', 'duration', 'instructions', 'is_active'])]
class MissionRecurrence extends Model
{
    /** @use HasFactory<MissionRecurrenceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'next_on' => 'date', 'is_active' => 'boolean', 'interval' => 'integer', 'duration' => 'integer'];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /** @return BelongsTo<ServiceTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ServiceTemplate::class, 'template_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}

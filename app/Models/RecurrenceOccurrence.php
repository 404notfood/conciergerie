<?php

namespace App\Models;

use Database\Factories\RecurrenceOccurrenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $concierge_id
 * @property int $mission_recurrence_id
 * @property Carbon $scheduled_on
 * @property string $status
 * @property array<string, mixed> $snapshot
 * @property int|null $mission_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property MissionRecurrence $recurrence
 * @property Mission $mission
 */
#[Fillable(['concierge_id', 'mission_recurrence_id', 'scheduled_on', 'status', 'snapshot', 'mission_id'])]
class RecurrenceOccurrence extends Model
{
    /** @use HasFactory<RecurrenceOccurrenceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['scheduled_on' => 'date', 'snapshot' => 'array'];
    }

    /** @return BelongsTo<MissionRecurrence, $this> */
    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(MissionRecurrence::class, 'mission_recurrence_id');
    }

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }
}

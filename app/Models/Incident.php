<?php

namespace App\Models;

use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $version
 * @property string $priority
 * @property int|null $responsible_user_id
 * @property int|null $repair_mission_id
 * @property string|null $resolution_notes
 * @property array<int,int>|null $resolution_photo_ids
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $shared_status
 * @property int $id
 * @property int $concierge_id
 * @property int $mission_id
 * @property int $author_id
 * @property string $operation_id
 * @property string $description
 * @property string $status
 * @property string|null $client_message
 * @property list<int>|null $shared_photo_ids
 * @property int|null $shared_by
 * @property Carbon|null $shared_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Mission $mission
 * @property User $author
 */
#[Fillable(['concierge_id', 'mission_id', 'author_id', 'operation_id', 'description', 'status', 'client_message', 'shared_photo_ids', 'shared_by', 'shared_at', 'version', 'priority', 'responsible_user_id', 'repair_mission_id', 'resolution_notes', 'resolution_photo_ids', 'resolved_at', 'resolved_by', 'shared_status'])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['shared_photo_ids' => 'array', 'shared_at' => 'datetime', 'resolved_at' => 'datetime', 'resolution_photo_ids' => 'array', 'version' => 'integer'];
    }

    /** @return HasMany<IncidentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(IncidentEvent::class)->orderByDesc('id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return BelongsTo<Mission, $this> */
    public function repairMission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'repair_mission_id');
    }

    /** @return BelongsTo<Mission, $this> */

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

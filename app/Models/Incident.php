<?php

namespace App\Models;

use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
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
#[Fillable(['concierge_id', 'mission_id', 'author_id', 'operation_id', 'description', 'status', 'client_message', 'shared_photo_ids', 'shared_by', 'shared_at'])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['shared_photo_ids' => 'array', 'shared_at' => 'datetime'];
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

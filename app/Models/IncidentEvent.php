<?php

namespace App\Models;

use Database\Factories\IncidentEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $concierge_id
 * @property int $incident_id
 * @property int $user_id
 * @property string $action
 * @property string $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $user
 */
#[Fillable(['concierge_id', 'incident_id', 'user_id', 'action', 'description'])]
class IncidentEvent extends Model
{
    /** @use HasFactory<IncidentEventFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

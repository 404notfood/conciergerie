<?php

namespace App\Models;

use Database\Factories\StaffAvailabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $concierge_id
 * @property int $user_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $user
 */
#[Fillable(['concierge_id', 'user_id', 'starts_at', 'ends_at', 'note'])]
class StaffAvailability extends Model
{
    /** @use HasFactory<StaffAvailabilityFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

<?php

namespace App\Models;

use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $concierge_id
 * @property int $user_id
 * @property string $role
 * @property int|null $client_id
 * @property string|null $team
 * @property string|null $company
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $user
 * @property Concierge $concierge
 * @property Client|null $client
 */
#[Fillable(['concierge_id', 'user_id', 'role', 'client_id', 'team', 'company'])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Concierge, $this> */
    public function concierge(): BelongsTo
    {
        return $this->belongsTo(Concierge::class, 'concierge_id');
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}

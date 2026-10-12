<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $concierge_id
 * @property int $user_id
 * @property string $role
 * @property int|null $client_id
 * @property string|null $team
 * @property string|null $company
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property User $user
 * @property Concierge $concierge
 * @property Client|null $client
 */
#[Fillable(['concierge_id', 'user_id', 'role', 'client_id', 'team', 'company'])]
class Membership extends Model
{
    /** @use HasFactory<\Database\Factories\MembershipFactory> */
    use HasFactory;
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    /** @return BelongsTo<Concierge, $this> */
    public function concierge(): BelongsTo { return $this->belongsTo(Concierge::class, 'concierge_id'); }
    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo { return $this->belongsTo(Client::class, 'client_id'); }
}

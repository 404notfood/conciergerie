<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['concierge_id', 'user_id', 'role', 'client_id', 'team', 'company'])]
class Membership extends Model
{
    /** @use HasFactory<\Database\Factories\MembershipFactory> */
    use HasFactory;
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function concierge(): BelongsTo { return $this->belongsTo(Concierge::class, 'concierge_id'); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class, 'client_id'); }
}

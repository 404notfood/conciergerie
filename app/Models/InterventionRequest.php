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
 * @property int $property_id
 * @property int $author_id
 * @property string $title
 * @property string $description
 * @property string $status
 * @property int|null $mission_id
 * @property string|null $decision
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property Property $property
 * @property User $author
 */
#[Fillable(['concierge_id', 'property_id', 'author_id', 'title', 'description', 'status', 'mission_id', 'decision'])]
class InterventionRequest extends Model
{
    /** @use HasFactory<\Database\Factories\InterventionRequestFactory> */
    use HasFactory;
    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo { return $this->belongsTo(Property::class, 'property_id'); }
    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}

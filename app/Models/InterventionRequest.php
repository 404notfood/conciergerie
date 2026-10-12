<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['concierge_id', 'property_id', 'author_id', 'title', 'description', 'status', 'mission_id', 'decision'])]
class InterventionRequest extends Model
{
    /** @use HasFactory<\Database\Factories\InterventionRequestFactory> */
    use HasFactory;
    public function property(): BelongsTo { return $this->belongsTo(Property::class, 'property_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}

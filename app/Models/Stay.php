<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['concierge_id', 'property_id', 'title', 'starts_on', 'ends_on', 'source', 'external_uid', 'kind'])]
class Stay extends Model
{
    /** @use HasFactory<\Database\Factories\StayFactory> */
    use HasFactory;
    public function property(): BelongsTo { return $this->belongsTo(Property::class, 'property_id'); }
}

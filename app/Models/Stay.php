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
 * @property string $title
 * @property string $starts_on
 * @property string $ends_on
 * @property string $source
 * @property string|null $external_uid
 * @property string $kind
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property Property $property
 */
#[Fillable(['concierge_id', 'property_id', 'title', 'starts_on', 'ends_on', 'source', 'external_uid', 'kind'])]
class Stay extends Model
{
    /** @use HasFactory<\Database\Factories\StayFactory> */
    use HasFactory;
    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo { return $this->belongsTo(Property::class, 'property_id'); }
}

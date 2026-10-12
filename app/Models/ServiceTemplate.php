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
 * @property string $name
 * @property list<string> $checklist
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
#[Fillable(['concierge_id', 'name', 'checklist'])]
class ServiceTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceTemplateFactory> */
    use HasFactory;
    protected function casts(): array { return ['checklist' => 'array']; }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['concierge_id', 'name', 'checklist'])]
class ServiceTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceTemplateFactory> */
    use HasFactory;
    protected function casts(): array { return ['checklist' => 'array']; }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['concierge_id', 'mission_id', 'author_id', 'operation_id', 'description', 'status', 'client_message', 'shared_photo_ids', 'shared_by', 'shared_at'])]
class Incident extends Model
{
    /** @use HasFactory<\Database\Factories\IncidentFactory> */
    use HasFactory;
    protected function casts(): array { return ['shared_photo_ids' => 'array', 'shared_at' => 'datetime']; }
    public function mission(): BelongsTo { return $this->belongsTo(Mission::class, 'mission_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
}

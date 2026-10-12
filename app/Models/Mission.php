<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['concierge_id', 'property_id', 'assigned_user_id', 'title', 'kind', 'scheduled_at', 'duration', 'status', 'version', 'checklist', 'instructions'])]
class Mission extends Model
{
    /** @use HasFactory<\Database\Factories\MissionFactory> */
    use HasFactory;
    protected function casts(): array { return ['scheduled_at' => 'datetime', 'checklist' => 'array', 'version' => 'integer']; }
    public function property(): BelongsTo { return $this->belongsTo(Property::class, 'property_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function reports(): HasMany { return $this->hasMany(Report::class, 'mission_id')->orderByDesc('id'); }
    public function incidents(): HasMany { return $this->hasMany(Incident::class, 'mission_id')->orderByDesc('id'); }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['concierge_id', 'mission_id', 'author_id', 'operation_id', 'notes', 'answers', 'status', 'reviewer_id', 'feedback', 'reviewed_at'])]
class Report extends Model
{
    /** @use HasFactory<\Database\Factories\ReportFactory> */
    use HasFactory;
    protected function casts(): array { return ['answers' => 'array', 'reviewed_at' => 'datetime']; }
    public function mission(): BelongsTo { return $this->belongsTo(Mission::class, 'mission_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function photos(): HasMany { return $this->hasMany(Photo::class, 'report_id')->orderByDesc('id'); }
}

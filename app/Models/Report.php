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
 * @property int $mission_id
 * @property int $author_id
 * @property string $operation_id
 * @property string $notes
 * @property list<bool> $answers
 * @property string $status
 * @property int|null $reviewer_id
 * @property string|null $feedback
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property Mission $mission
 * @property User $author
 * @property User|null $reviewer
 * @property \Illuminate\Database\Eloquent\Collection<int, Photo> $photos
 */
#[Fillable(['concierge_id', 'mission_id', 'author_id', 'operation_id', 'notes', 'answers', 'status', 'reviewer_id', 'feedback', 'reviewed_at'])]
class Report extends Model
{
    /** @use HasFactory<\Database\Factories\ReportFactory> */
    use HasFactory;
    protected function casts(): array { return ['answers' => 'array', 'reviewed_at' => 'datetime']; }
    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo { return $this->belongsTo(Mission::class, 'mission_id'); }
    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
    /** @return HasMany<Photo, $this> */
    public function photos(): HasMany { return $this->hasMany(Photo::class, 'report_id')->orderByDesc('id'); }
}

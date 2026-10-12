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
 * @property int $report_id
 * @property int $author_id
 * @property string $path
 * @property string $stage
 * @property \Illuminate\Support\Carbon|null $captured_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property Report $report
 */
#[Fillable(['concierge_id', 'report_id', 'author_id', 'path', 'stage', 'captured_at'])]
#[Hidden(['path'])]
class Photo extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoFactory> */
    use HasFactory;
    protected function casts(): array { return ['captured_at' => 'datetime']; }
    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo { return $this->belongsTo(Report::class, 'report_id'); }
}

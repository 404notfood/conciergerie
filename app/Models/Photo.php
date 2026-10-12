<?php

namespace App\Models;

use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $concierge_id
 * @property int $report_id
 * @property int $author_id
 * @property int $size_bytes
 * @property string $path
 * @property string $stage
 * @property Carbon|null $captured_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Report $report
 */
#[Fillable(['concierge_id', 'report_id', 'author_id', 'path', 'size_bytes', 'stage', 'captured_at'])]
#[Hidden(['path'])]
class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['captured_at' => 'datetime'];
    }

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }
}

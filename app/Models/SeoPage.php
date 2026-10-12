<?php

namespace App\Models;

use Database\Factories\SeoPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $path
 * @property string $kind
 * @property string $title
 * @property string $description
 * @property string $heading
 * @property string $intro
 * @property array<int, array{heading: string, text: string}> $sections
 * @property array<int, array{question: string, answer: string}> $faq
 * @property string $status
 * @property bool $noindex
 * @property ?string $keyword
 * @property ?string $social_title
 * @property ?string $social_description
 * @property ?Carbon $published_at
 * @property Carbon $updated_at
 */
#[Fillable(['path', 'kind', 'title', 'description', 'heading', 'intro', 'sections', 'faq', 'status', 'noindex', 'keyword', 'social_title', 'social_description', 'published_at'])]
class SeoPage extends Model
{
    /** @use HasFactory<SeoPageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['sections' => 'array', 'faq' => 'array', 'noindex' => 'boolean', 'published_at' => 'datetime'];
    }
}

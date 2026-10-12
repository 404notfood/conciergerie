<?php

namespace App\Models;

use Database\Factories\ServiceTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $concierge_id
 * @property string $name
 * @property list<string> $checklist
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['concierge_id', 'name', 'checklist'])]
class ServiceTemplate extends Model
{
    /** @use HasFactory<ServiceTemplateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['checklist' => 'array'];
    }
}

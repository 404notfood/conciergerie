<?php

namespace App\Models;

use Database\Factories\ConciergeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name'])]
class Concierge extends Model
{
    /** @use HasFactory<ConciergeFactory> */
    use HasFactory;
}

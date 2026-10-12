<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/**
 * @property int $id
 * @property string $name
 * @property int $monthly_price_cents
 * @property array<string,int> $limit_bonus
 * @property array<string,bool> $features
 * @property bool $is_active
 * @property string|null $description
 */
#[Fillable(['name', 'monthly_price_cents', 'limit_bonus', 'features', 'is_active', 'description'])]
class PlanAddon extends Model {
    /** @use HasFactory<\Database\Factories\PlanAddonFactory> */
    use HasFactory;
    protected function casts(): array {return ['monthly_price_cents' => 'integer', 'limit_bonus' => 'array', 'features' => 'array', 'is_active' => 'boolean'];}
}

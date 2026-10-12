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
 * @property array<string,int|null> $limits
 * @property array<string,bool> $features
 * @property bool $is_active
 * @property bool $is_public
 * @property string|null $description
 */
#[Fillable(['name', 'monthly_price_cents', 'limits', 'features', 'is_active', 'is_public', 'description'])]
class SubscriptionPlan extends Model {
    /** @use HasFactory<\Database\Factories\SubscriptionPlanFactory> */
    use HasFactory;
    protected function casts(): array {return ['monthly_price_cents' => 'integer', 'limits' => 'array', 'features' => 'array', 'is_active' => 'boolean', 'is_public' => 'boolean'];}
}

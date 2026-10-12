<?php

namespace App\Models;

use Database\Factories\ConciergeSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $concierge_id
 * @property int $subscription_plan_id
 * @property string $status
 * @property array<string,mixed> $terms
 * @property array<string,int|null>|null $limit_overrides
 * @property array<string,bool>|null $feature_overrides
 * @property int|null $price_override_cents
 * @property string $starts_on
 * @property Carbon|null $expires_at
 * @property string|null $notes
 * @property SubscriptionPlan $plan
 * @property Concierge $concierge
 */
#[Fillable(['concierge_id', 'subscription_plan_id', 'status', 'terms', 'limit_overrides', 'feature_overrides', 'price_override_cents', 'starts_on', 'expires_at', 'notes'])]
#[Hidden(['notes'])]
class ConciergeSubscription extends Model
{
    /** @use HasFactory<ConciergeSubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['terms' => 'array', 'limit_overrides' => 'array', 'feature_overrides' => 'array', 'price_override_cents' => 'integer', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** @return BelongsTo<Concierge, $this> */
    public function concierge(): BelongsTo
    {
        return $this->belongsTo(Concierge::class, 'concierge_id');
    }
}

<?php

namespace App\Models;

use Database\Factories\MissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $concierge_id
 * @property int $property_id
 * @property string|null $assigned_company
 * @property int|null $assigned_user_id
 * @property string $title
 * @property string $kind
 * @property Carbon $scheduled_at
 * @property int $duration
 * @property string $status
 * @property int $version
 * @property list<string> $checklist
 * @property string|null $instructions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Property $property
 * @property User|null $assignee
 * @property Collection<int, Report> $reports
 * @property Collection<int, Incident> $incidents
 */
#[Fillable(['concierge_id', 'property_id', 'assigned_user_id', 'assigned_company', 'title', 'kind', 'scheduled_at', 'duration', 'status', 'version', 'checklist', 'instructions'])]
class Mission extends Model
{
    /** @use HasFactory<MissionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'checklist' => 'array', 'version' => 'integer'];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return HasMany<Report, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'mission_id')->orderByDesc('id');
    }

    /** @return HasMany<Incident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'mission_id')->orderByDesc('id');
    }
}

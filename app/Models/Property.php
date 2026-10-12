<?php

namespace App\Models;

use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $concierge_id
 * @property int $client_id
 * @property string $name
 * @property string $address
 * @property string $kind
 * @property string|null $instructions
 * @property string|null $access_code
 * @property bool $offline_access
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Client $client
 */
#[Fillable(['concierge_id', 'client_id', 'name', 'address', 'kind', 'instructions', 'access_code', 'offline_access'])]
#[Hidden(['access_code'])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['access_code' => 'encrypted', 'offline_access' => 'boolean'];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}

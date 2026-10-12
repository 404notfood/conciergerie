<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['concierge_id', 'client_id', 'name', 'address', 'kind', 'instructions', 'access_code', 'offline_access'])]
#[Hidden(['access_code'])]
class Property extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyFactory> */
    use HasFactory;
    protected function casts(): array { return ['access_code' => 'encrypted', 'offline_access' => 'boolean']; }
    public function client(): BelongsTo { return $this->belongsTo(Client::class, 'client_id'); }
}

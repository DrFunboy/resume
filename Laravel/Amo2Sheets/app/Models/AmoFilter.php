<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int id
 * @property string uuid
 * @property string name
 * @property int account_id
 * @property string pipeline_id
 * @property string filter_url
 * @property int amo_author
 * @property ?string comment
 * @property string created_at
 * @property string updated_at
 *
 * @property-read boolean used
 * @property-read AmoAccount account
 *
 * @method static Builder|AmoFilter query()
 */
class AmoFilter extends Model
{
    use HasUuids;
    protected $table = 'amo_filter';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(AmoConnection::class, 'filter_id', 'id');
    }

    public function used(): Attribute
    {
        return new Attribute(
            get: fn() => $this->connections()->exists()
        );
    }
}

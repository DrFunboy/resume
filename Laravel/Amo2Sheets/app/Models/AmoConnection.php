<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int id
 * @property string uuid
 * @property int account_id
 * @property int filter_id
 * @property string sheet_id
 * @property array sheet_fields
 * @property string date_sync
 * @property int amo_author
 * @property string created_at
 * @property string updated_at
 *
 * @property-read AmoFilter filter
 *
 * @method static Builder|AmoConnection query()
 */
class AmoConnection extends Model
{
    use HasUuids;
    protected $table = 'amo_connection';
    protected $casts = [
        'sheet_fields' => 'array',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AmoAccount::class);
    }

    public function filter(): BelongsTo
    {
        return $this->belongsTo(AmoFilter::class);
    }

    public function scopeFilter(Builder $query, array $filterData): Builder
    {
        if (!empty($filterData['domain'])) {
            $query->whereHas('AmoAccount', function (Builder $q) use ($filterData) {
                $q->where('domain', $filterData['domain']);
            });
        }

        return $query;
    }
}

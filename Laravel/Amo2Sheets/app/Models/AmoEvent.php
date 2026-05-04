<?php

namespace App\Models;

use App\Enums\AmoEventStatus;
use App\Enums\AmoEventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int id
 * @property string external_id
 * @property AmoEventType type
 * @property AmoEventStatus status
 * @property int connection_id
 * @property int try_count
 * @property array event_body
 * @property string date_start
 * @property string date_end
 * @property string created_at
 * @property string updated_at
 *
 * @property-read AmoConnection connection
 *
 * @method static Builder|AmoEvent query()
 */
class AmoEvent extends Model
{
    protected $table = 'amo_event';

    protected $casts = [
        'event_body' => 'array',
    ];

    public function connection(): HasOne
    {
        return $this->hasOne(AmoConnection::class, 'id', 'connection_id');
    }

}

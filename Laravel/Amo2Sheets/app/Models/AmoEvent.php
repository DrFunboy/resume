<?php

namespace App\Models;

use App\Enums\AmoEventStatus;
use App\Enums\AmoEventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int id
 * @property string external_id
 * @property AmoEventType type
 * @property AmoEventStatus status
 * @property int pipeline_id
 * @property int try_count
 * @property array event_body
 * @property string date_start
 * @property string date_end
 * @property string created_at
 * @property string updated_at
 *
 * @property-read AmoFilter filters
 *
 * @method static Builder|AmoEvent query()
 */
class AmoEvent extends Model
{
    protected $table = 'amo_event';

    protected $casts = [
        'event_body' => 'array',
    ];

    public function filters(): hasManyThrough
    {
        return $this->hasManyThrough(
            AmoFilter::class,
            AmoFilterPipeline::class,
            'pipeline_id',
            'id',
            'pipeline_id',
            'filter_id'
        );
    }
}

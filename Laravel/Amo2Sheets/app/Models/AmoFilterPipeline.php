<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int id
 * @property int pipeline_id
 * @property int filter_id
 */
class AmoFilterPipeline extends Model
{
    protected $table = 'amo_filter_pipeline';

    protected $fillable = [
        'pipeline_id',
    ];

    public function filter(): BelongsTo
    {
        return $this->belongsTo(AmoFilter::class);
    }
}

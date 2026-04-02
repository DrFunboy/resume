<?php

namespace App\Models;

use App\Enums\CompanyGroupStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $year
 * @property string $status_group
 * @property string $status_code
 * @property int companies_count
 * @property Carbon calculated_at
 * @property int data_version
 * @property Carbon $created_at
 *
 * @method static self|Builder query()
 */
class Aggregate extends Model
{
    protected $table = 'aggregates';

    protected $casts = [
        'status_group' => CompanyGroupStatus::class
    ];
}

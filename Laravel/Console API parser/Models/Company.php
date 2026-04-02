<?php

namespace App\Models;

use App\Enums\CompanyGroupStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $inn
 * @property int $region_code
 * @property string $main_okved
 * @property Carbon $registration_date
 * @property ?Carbon $liquidation_date
 * @property string $raw_status
 * @property string $status_group
 * @property string $status_code
 * @property Carbon $last_fns_sync_at
 * @property Carbon $updated_at
 *
 * @method static self|Builder query()
 */
class Company extends Model
{

    protected $table = 'companies';

    protected $casts = [
        'last_fns_sync_at' => 'datetime',
        'updated_at' => 'datetime',
        'status_group' => CompanyGroupStatus::class
    ];

}

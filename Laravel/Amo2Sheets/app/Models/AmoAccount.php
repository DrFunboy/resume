<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int id
 * @property string client_id
 * @property string domain
 * @property string google_refresh
 * @property string amo_refresh
 * @property string amo_secret
 *
 * @method static Builder|AmoAccount query()
 */
class AmoAccount extends Model
{
    protected $table = 'amo_account';

    public function connections(): HasMany
    {
        return $this->hasMany(AmoConnection::class, 'account_id', 'id');
    }

    public function filters(): HasMany
    {
        return $this->hasMany(AmoFilter::class, 'account_id', 'id');
    }

}

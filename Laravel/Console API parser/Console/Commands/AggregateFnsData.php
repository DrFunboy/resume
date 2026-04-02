<?php

namespace App\Console\Commands;

use App\Extensions\Company\CompanyService;
use Illuminate\Console\Command;

class AggregateFnsData extends Command
{
    protected $signature = 'app:aggregate-fns';
    protected $description = 'Aggregate and save data from FNS API';
    public function handle()
    {
        CompanyService::aggregateData();
    }
}

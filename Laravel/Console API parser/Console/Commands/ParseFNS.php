<?php

namespace App\Console\Commands;

use App\Extensions\Company\CompanyService;
use Illuminate\Console\Command;

class ParseFNS extends Command
{
    protected $signature = 'app:parse-fns';
    protected $description = 'Load company data from FNS API';
    public function handle()
    {
        CompanyService::parseFNS();
    }
}

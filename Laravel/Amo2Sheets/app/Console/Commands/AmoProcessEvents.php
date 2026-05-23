<?php

namespace App\Console\Commands;

use App\Services\Amo2SheetsService;
use Illuminate\Console\Command;

class AmoProcessEvents extends Command
{
    protected $signature = 'amo:process-events';
    protected $description = 'Processes events from AmoCRM';
    public function handle(): void
    {
        Amo2SheetsService::processEvents();
    }
}

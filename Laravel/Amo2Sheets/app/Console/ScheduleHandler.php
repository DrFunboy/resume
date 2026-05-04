<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;

class ScheduleHandler
{
    public function __invoke(Schedule $schedule): void
    {
        $schedule->command('amo:process-events')->everyTwoMinutes();
    }
}

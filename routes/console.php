<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Host CRON (freehosting.lt panel, every minute) runs `schedule:run`.
Schedule::command('tournated:sync')->everyMinute()->withoutOverlapping(10);

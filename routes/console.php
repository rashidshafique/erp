<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// People: needs `php artisan schedule:run` every minute (cron), like any Laravel schedule.
\Illuminate\Support\Facades\Schedule::command('hr:close-attendance')->dailyAt('01:30');
\Illuminate\Support\Facades\Schedule::command('hr:leave-accrue')->dailyAt('02:00');

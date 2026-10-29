<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('stock:check-reorder')->hourly();
Schedule::command('moulding:prepare-list')->dailyAt('18:00')->when(fn () => \App\Support\WorkListRules::get('auto_prepare') === 'NEXT_DAY');
Schedule::command('moulding:prepare-list')->dailyAt('06:00')->when(fn () => \App\Support\WorkListRules::get('auto_prepare') === 'SAME_DAY');
Schedule::command('heats:add-due-charges')->everyMinute()->withoutOverlapping();

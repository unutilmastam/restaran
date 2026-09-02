<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler — cPanel cron orqali
|--------------------------------------------------------------------------
| * * * * * cd ~/apps/backend && php artisan schedule:run >> /dev/null 2>&1
*/

Schedule::command('orders:expire-drafts')->everyTenMinutes()->withoutOverlapping();

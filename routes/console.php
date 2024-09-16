<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();

Schedule::job(new \App\Jobs\CreateSummersJob)->everyTenMinutes()->withoutOverlapping();

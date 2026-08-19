<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly staff sync. Requires the Forge Scheduler (schedule:run every minute).
// withoutOverlapping guards against a slow run colliding with the next tick.
Schedule::command('app:sync-staff')->hourly()->withoutOverlapping();

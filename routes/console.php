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

// Daily notification digest, sent each morning in Reykjavík time.
Schedule::command('app:send-digest')->dailyAt('07:00')->timezone('Atlantic/Reykjavik');

// Poll Microsoft Graph for Teams presence and broadcast the changes. The command no-ops
// while MS_GRAPH_ENABLED is off, so this schedule is safe to ship before consent is granted.
Schedule::command('app:sync-presence')->everyMinute()->withoutOverlapping();

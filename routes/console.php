<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Mirror the YouTube channel hourly.
 *
 * withoutOverlapping matters on a free-tier container: a slow fetch must not
 * stack up a second run on top of the first.
 */
Schedule::command('youtube:sync')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Retainer billing.
 *
 * Runs daily rather than monthly: the command decides for itself whether each
 * project's billing day has arrived, so a missed day (container asleep, deploy,
 * outage) is picked up the next morning instead of skipping a whole month's
 * income. Generation is idempotent, so repeated runs cannot double-bill.
 */
Schedule::command('retainers:generate')
    ->dailyAt('06:00')
    ->withoutOverlapping();

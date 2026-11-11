<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Scheduled tasks (spec §16), in the Authority's timezone. Run the scheduler
 * every minute: `php artisan schedule:run` (cron) or `php artisan schedule:work`.
 */

Schedule::command('permits:expire')
    ->dailyAt('00:05')
    ->timezone(config('localization.display_timezone'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('exports:prune')
    ->dailyAt('03:00')
    ->timezone(config('localization.display_timezone'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('permits:notify-expiring')
    ->dailyAt((string) config('permits.notifications.reminder_time'))
    ->timezone(config('localization.display_timezone'))
    ->withoutOverlapping()
    ->onOneServer();

<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/*
 * The scheduled tasks (spec §16): times are Africa/Addis_Ababa.
 */

function scheduledCommand(string $command): Event
{
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, $command));

    expect($event)->not->toBeNull("{$command} is not scheduled");

    return $event;
}

it('expires permits daily at 00:05 Addis Ababa time, once', function () {
    $event = scheduledCommand('permits:expire');

    expect($event->expression)->toBe('5 0 * * *')
        ->and($event->timezone)->toBe('Africa/Addis_Ababa')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

it('sends expiring-soon reminders daily at the configured time', function () {
    $event = scheduledCommand('permits:notify-expiring');

    expect($event->expression)->toBe('0 8 * * *')
        ->and($event->timezone)->toBe('Africa/Addis_Ababa')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

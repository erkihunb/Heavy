<?php

namespace App\Jobs;

use App\Domain\Telegram\TelegramGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One staff alert to the Telegram chat. Queued so that a slow or unreachable
 * Telegram never holds up the person who made the change.
 */
class SendTelegramMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly string $text)
    {
        $this->afterCommit();
    }

    public function handle(TelegramGateway $telegram): void
    {
        // Switched off (or unset) since it was queued.
        if (! config('services.telegram.enabled') || ! $telegram->configured()) {
            return;
        }

        $telegram->send($this->text);
    }
}

<?php

namespace App\Domain\Telegram;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends a message through the Telegram Bot API to the staff chat set in
 * System settings → Telegram.
 */
final class TelegramGateway
{
    public function configured(): bool
    {
        return filled(config('services.telegram.bot_token')) && filled(config('services.telegram.chat_id'));
    }

    /**
     * @throws RuntimeException when the bot is not set up or Telegram refuses the message
     */
    public function send(string $text): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('The Telegram bot token or chat is not set.');
        }

        $response = Http::asJson()
            ->timeout(15)
            ->post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/sendMessage', [
                'chat_id' => config('services.telegram.chat_id'),
                'text' => $text,
                'disable_web_page_preview' => true,
            ]);

        if (! $response->successful() || $response->json('ok') !== true) {
            // The token is part of the URL: never put the request in the message.
            throw new RuntimeException('Telegram refused the message: '.(string) ($response->json('description') ?? $response->status()));
        }
    }
}

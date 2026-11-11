<?php

namespace App\Domain\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * A generic SMS provider: the message is posted as JSON
 * {to, message, sender} to the configured endpoint with a bearer token
 * (System settings → SMS). When the Authority chooses a provider (open
 * question §14.7) whose API differs, it gets its own implementation.
 */
final class HttpSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $url,
        private readonly string $token,
        private readonly ?string $sender = null,
    ) {}

    public function send(string $to, string $message): void
    {
        if ($this->url === '') {
            throw new RuntimeException('The SMS endpoint is not set.');
        }

        try {
            Http::withToken($this->token)
                ->acceptJson()
                ->timeout(15)
                ->post($this->url, array_filter([
                    'to' => $to,
                    'message' => $message,
                    'sender' => $this->sender,
                ], fn (?string $value): bool => $value !== null && $value !== ''))
                ->throw();
        } catch (Throwable $exception) {
            // The number is not repeated in the error (spec §15: no PII in logs).
            throw new RuntimeException('The SMS provider refused the message: '.class_basename($exception), 0, $exception);
        }
    }
}

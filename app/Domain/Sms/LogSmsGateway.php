<?php

namespace App\Domain\Sms;

use Illuminate\Support\Facades\Log;

/**
 * The SMS "provider" until a real one is chosen: writes the message to the
 * log. The phone number is masked (spec §15: no PII in logs); the text only
 * names the permit number and what happened.
 */
final class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS (log driver)', [
            'to' => self::mask($to),
            'message' => $message,
        ]);
    }

    /**
     * "0911223344" → "*******344".
     */
    public static function mask(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return str_repeat('*', max(0, strlen($digits) - 3)).substr($digits, -3);
    }
}

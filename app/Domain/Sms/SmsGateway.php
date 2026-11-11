<?php

namespace App\Domain\Sms;

use RuntimeException;

/**
 * Sends a text message (spec §11). The provider is an open question (§14.7):
 * the application only knows this contract, and a provider is added as a new
 * implementation selected by `SMS_DRIVER`.
 */
interface SmsGateway
{
    /**
     * @param  string  $to  the phone number as stored, e.g. "0911223344" or "+251911223344"
     *
     * @throws RuntimeException when the provider refuses the message
     */
    public function send(string $to, string $message): void;
}

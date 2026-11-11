<?php

namespace App\Notifications\Channels;

use App\Domain\Sms\SmsGateway;
use App\Notifications\PermitNotification;
use Illuminate\Notifications\Notification;

/**
 * Delivers a notification's text message through the configured SMS gateway.
 * Notifications opt in with `via()` returning `SmsChannel::class`.
 */
final class SmsChannel
{
    public function __construct(private readonly SmsGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof PermitNotification || ! method_exists($notifiable, 'routeNotificationFor')) {
            return;
        }

        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (! is_string($to) || trim($to) === '') {
            return;
        }

        $this->gateway->send($to, $notification->toSms($notifiable));
    }
}

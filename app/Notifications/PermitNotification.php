<?php

namespace App\Notifications;

use App\Models\Permit;
use App\Notifications\Channels\SmsChannel;
use App\Support\EthiopianCalendar;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * What every message about a permit shares (spec §11): queued, sent only
 * after the change is committed, by e-mail to the members of the permit's
 * organization and by SMS to the organization's phone, in the recipient's
 * language. The wording lives in lang/{am,en}/notifications.php under the
 * notification's key.
 *
 * Messages name the permit and what happened, never fees in SMS, receipt
 * numbers, staff names or the verification token.
 */
abstract class PermitNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly Permit $permit, public readonly ?string $note = null)
    {
        $this->afterCommit();
    }

    /**
     * The key under `notifications.` for this message.
     */
    abstract protected function key(): string;

    /**
     * @return list<string|class-string>
     */
    public function via(object $notifiable): array
    {
        // Users are reached by e-mail; the organization's phone is an on-demand route.
        return $notifiable instanceof AnonymousNotifiable ? [SmsChannel::class] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = $this->replacements();
        $key = 'notifications.'.$this->key();

        $mail = (new MailMessage)
            ->subject(__("{$key}.subject", $replace))
            ->greeting(__('notifications.greeting'))
            ->line(__("{$key}.body", $replace));

        if ($this->note !== null && trim($this->note) !== '') {
            $mail->line(__('notifications.note', ['note' => trim($this->note)]));
        }

        // No link: applicants have no pages of their own; the office handles the request.
        return $mail
            ->salutation(__('notifications.salutation', ['authority' => __('common.authority')]));
    }

    public function toSms(object $notifiable): string
    {
        return __('notifications.'.$this->key().'.sms', $this->replacements());
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(): array
    {
        $permit = $this->permit;

        return [
            'number' => (string) ($permit->permit_number ?? ''),
            'type' => __("permits.types.{$permit->permit_type->value}"),
            'from' => EthiopianCalendar::label($permit->valid_from, app()->getLocale()),
            'to' => EthiopianCalendar::label($permit->valid_to, app()->getLocale()),
            'fee' => $permit->fee_cents === null ? '' : Money::format($permit->fee_cents, $permit->currency),
        ];
    }
}

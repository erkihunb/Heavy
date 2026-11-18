<?php

namespace App\Notifications;

use App\Models\RequestLetter;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One message for a request letter decided or issued together, instead of
 * one per vehicle: how many of its vehicles were approved (and the total to
 * pay), refused, or issued. Sent like the messages about one permit (see
 * PermitNotification): queued, after commit, by e-mail and SMS, in the
 * recipient's language. The wording is under notifications.letter_{what}.
 */
final class LetterNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    /**
     * @param  'approved'|'rejected'|'issued'  $what
     */
    public function __construct(
        public readonly RequestLetter $letter,
        public readonly string $what,
        public readonly int $count,
        public readonly ?int $totalCents = null,
        public readonly ?string $note = null,
        public readonly string $currency = 'ETB',
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string|class-string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return [SmsChannel::class];
        }

        return $notifiable instanceof User && ! $notifiable->wantsEmailNotifications() ? [] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = 'notifications.letter_'.$this->what;
        $replace = $this->replacements();

        $mail = (new MailMessage)
            ->subject(__("{$key}.subject", $replace))
            ->greeting(__('notifications.greeting'))
            ->line(__("{$key}.body", $replace));

        if ($this->note !== null && trim($this->note) !== '') {
            $mail->line(__('notifications.note', ['note' => trim($this->note)]));
        }

        return $mail->salutation(__('notifications.salutation', ['authority' => __('common.authority')]));
    }

    public function toSms(object $notifiable): string
    {
        return __('notifications.letter_'.$this->what.'.sms', $this->replacements());
    }

    /**
     * @return array<string, string>
     */
    private function replacements(): array
    {
        return [
            'letter' => $this->letter->letter_number,
            'count' => (string) $this->count,
            'total' => $this->totalCents === null ? '' : Money::format($this->totalCents, $this->currency),
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a member of staff whose account an Admin opened: a link to choose
 * a password. The link expires like any password-reset link; after that the
 * person uses "Forgot password".
 */
class StaffAccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        return (new MailMessage)
            ->subject(__('admin.users.mail.subject', ['app' => config('app.name')]))
            ->line(__('admin.users.mail.body'))
            ->action(__('admin.users.mail.action'), $url)
            ->line(__('admin.users.mail.expiry', ['minutes' => (int) config('auth.passwords.users.expire')]))
            ->line(__('admin.users.mail.two_factor'));
    }
}

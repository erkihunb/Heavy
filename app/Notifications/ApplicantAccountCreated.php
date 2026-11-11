<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to an applicant a Registrar registered at the office: a link to
 * choose a password. The link expires like any password-reset link; after
 * that the person uses "Forgot password".
 */
class ApplicantAccountCreated extends Notification implements ShouldQueue
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
        $locale = $notifiable->locale ?? app()->getLocale();

        return (new MailMessage)
            ->subject(__('applicants.mail.subject', ['app' => config('app.name')], $locale))
            ->greeting(__('applicants.mail.greeting', ['name' => $notifiable->name], $locale))
            ->line(__('applicants.mail.body', [], $locale))
            ->action(__('applicants.mail.action', [], $locale), $url)
            ->line(__('applicants.mail.expiry', ['minutes' => (int) config('auth.passwords.users.expire')], $locale));
    }
}

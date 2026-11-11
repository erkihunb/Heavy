<?php

namespace App\Notifications;

use App\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a member of staff their export is ready (spec §11: "emailed /
 * downloadable when done"). The file itself is not attached: it is
 * downloaded from the exports page after signing in.
 */
class ExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Export $export) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('reports.exports.mail.subject', ['id' => $this->export->id]))
            ->line(__('reports.exports.mail.body', [
                'dataset' => __("reports.exports.datasets.{$this->export->dataset->value}"),
                'rows' => (int) $this->export->rows,
            ]))
            ->action(__('reports.exports.mail.action'), route('staff.exports.index'))
            ->line(__('reports.exports.mail.expiry', ['days' => (int) config('permits.exports.keep_days')]));
    }
}

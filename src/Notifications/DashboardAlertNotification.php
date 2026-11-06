<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DashboardAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $url,
    ) {}

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
            ->subject($this->title)
            ->line($this->body)
            ->action(__('filament-dashboards::dashboards.digest.open'), $this->url);
    }
}

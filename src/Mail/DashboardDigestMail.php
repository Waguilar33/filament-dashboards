<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Waguilar\FilamentDashboards\Widgets\Stats\StatCardResult;

class DashboardDigestMail extends Mailable
{
    use Queueable;

    /**
     * @param  list<array{name: string, cards: list<StatCardResult>}>  $sections
     * @param  list<string>  $others
     */
    public function __construct(
        public string $dashboardName,
        public string $url,
        public string $rangeLabel,
        public array $sections,
        public array $others,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('filament-dashboards::dashboards.digest.subject', ['dashboard' => $this->dashboardName, 'range' => $this->rangeLabel]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'filament-dashboards::mail.digest');
    }
}

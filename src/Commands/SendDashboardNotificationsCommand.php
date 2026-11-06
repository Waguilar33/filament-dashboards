<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Commands;

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Waguilar\FilamentDashboards\Actions\BuildDashboardDigest;
use Waguilar\FilamentDashboards\Actions\EvaluateStatsCards;
use Waguilar\FilamentDashboards\Actions\RunAsRecipient;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\Enums\AlertOperator;
use Waguilar\FilamentDashboards\Enums\DigestFrequency;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Mail\DashboardDigestMail;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardAlert;
use Waguilar\FilamentDashboards\Models\DashboardSubscription;
use Waguilar\FilamentDashboards\Notifications\DashboardAlertNotification;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class SendDashboardNotificationsCommand extends Command
{
    public const SEND_HOUR = 7;

    protected $signature = 'dashboards:notify';

    protected $description = 'Send due dashboard emails and check dashboard alerts. Schedule it hourly.';

    public function handle(RunAsRecipient $runAsRecipient, BuildDashboardDigest $buildDigest, EvaluateStatsCards $evaluateCards): int
    {
        foreach (DashboardSubscription::query()->with(['user', 'tenant', 'dashboard'])->lazyById() as $subscription) {
            if (! $subscription->user instanceof Model || ! $subscription->dashboard instanceof Dashboard) {
                $subscription->delete();

                continue;
            }

            $runAsRecipient->handle($subscription->panel, $subscription->user, $subscription->tenant, fn () => $this->sendDigest($subscription, $subscription->user, $subscription->dashboard, $buildDigest));
        }

        foreach (DashboardAlert::query()->with(['user', 'tenant', 'dashboard', 'builderWidget'])->lazyById() as $alert) {
            if (! $alert->user instanceof Model || ! $alert->dashboard instanceof Dashboard || ! $alert->builderWidget instanceof BuilderWidget) {
                $alert->delete();

                continue;
            }

            $runAsRecipient->handle($alert->panel, $alert->user, $alert->tenant, fn () => $this->checkAlert($alert, $alert->user, $alert->dashboard, $evaluateCards));
        }

        return self::SUCCESS;
    }

    protected function sendDigest(DashboardSubscription $subscription, Model $user, Dashboard $dashboard, BuildDashboardDigest $buildDigest): void
    {
        $now = CarbonImmutable::now(FilamentDashboardsPlugin::get()->getTimezone());
        $since = $subscription->frequency === DigestFrequency::Weekly ? $now->startOfWeek() : $now->startOfDay();

        if ($now->hour < self::SEND_HOUR || ($subscription->last_sent_at !== null && $subscription->last_sent_at->greaterThanOrEqualTo($since))) {
            return;
        }

        if (! $this->canView($dashboard, $user, $subscription->tenant)) {
            return;
        }

        $range = $subscription->frequency === DigestFrequency::Weekly
            ? new DateRange($now->subWeek()->startOfWeek(), $now->subWeek()->endOfWeek())
            : new DateRange($now->subDay()->startOfDay(), $now->subDay()->endOfDay());
        $digest = $buildDigest->handle($dashboard, $user, $subscription->tenant, $range);

        Mail::to($user)->send(new DashboardDigestMail(
            $dashboard->name,
            DashboardResource::getUrl('view', ['record' => $dashboard], tenant: $subscription->tenant),
            $range->start->isSameDay($range->end)
                ? $range->start->translatedFormat('j M Y')
                : $range->start->translatedFormat('j M').' – '.$range->end->translatedFormat('j M Y'),
            $digest['sections'],
            $digest['others'],
        ));

        $subscription->update(['last_sent_at' => now()]);
    }

    protected function checkAlert(DashboardAlert $alert, Model $user, Dashboard $dashboard, EvaluateStatsCards $evaluateCards): void
    {
        $widget = BuilderWidget::query()->inScope($alert->panel, $alert->tenant)->visibleTo($user)->find($alert->builder_widget_id);
        $card = $widget?->keyedCards()[$alert->card_key] ?? null;

        if (! is_array($card) || ! $this->canView($dashboard, $user, $alert->tenant)) {
            return;
        }

        $now = CarbonImmutable::now(FilamentDashboardsPlugin::get()->getTimezone());
        $today = new DateRange($now->startOfDay(), $now->endOfDay());

        if ($alert->operator === AlertOperator::Above) {
            $result = $evaluateCards->handle([$card], $user, $alert->tenant, $today)[0] ?? null;

            if ($result === null || $result->needsAttention || $result->value === null) {
                return;
            }

            $crossed = $result->value > $alert->threshold;

            if ($crossed && ! $alert->triggered) {
                $this->notify($user, $dashboard, $alert, $result->label, $result->formatted);
            }

            $alert->update(['triggered' => $crossed]);

            return;
        }

        $range = $evaluateCards->range($card, $today);
        $previous = $range?->previous(is_string($card['period'] ?? null) ? $card['period'] : null);
        $period = $previous?->start->toDateString() ?? $now->toDateString();

        if ($now->hour < self::SEND_HOUR || $alert->last_period === $period) {
            return;
        }

        $result = $evaluateCards->handle([[...$card, 'period' => 'dashboard']], $user, $alert->tenant, $previous ?? $today)[0] ?? null;

        if ($result !== null && ! $result->needsAttention && $result->value !== null && $result->value < $alert->threshold) {
            $this->notify($user, $dashboard, $alert, $result->label, $result->formatted);
        }

        $alert->update(['last_period' => $period]);
    }

    protected function canView(Dashboard $dashboard, Model $user, ?Model $tenant): bool
    {
        return Dashboard::query()->inScope($dashboard->panel, $tenant)->visibleTo($user)->whereKey($dashboard->getKey())->exists();
    }

    protected function notify(Model $user, Dashboard $dashboard, DashboardAlert $alert, string $label, string $value): void
    {
        $title = __("filament-dashboards::dashboards.alerts.notification.{$alert->operator->value}", ['card' => $label]);
        $body = __('filament-dashboards::dashboards.alerts.notification.body', ['dashboard' => $dashboard->name, 'value' => $value]);
        $url = DashboardResource::getUrl('view', ['record' => $dashboard], tenant: $alert->tenant);

        if (method_exists($user, 'notify')) {
            $user->notify(new DashboardAlertNotification($title, $body, $url));
        }

        if (Filament::getCurrentPanel()?->hasDatabaseNotifications()) {
            FilamentNotification::make()->title($title)->body($body)->warning()->sendToDatabase($user);
        }
    }
}

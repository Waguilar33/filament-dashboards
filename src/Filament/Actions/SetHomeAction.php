<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Filament\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Waguilar\FilamentDashboards\Actions\SetHomeDashboard;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardHome;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class SetHomeAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'setAsHome';
    }

    public static function isHome(Dashboard $dashboard): bool
    {
        $user = DashboardResource::user();
        $tenant = Filament::getTenant();

        return DashboardHome::query()
            ->where('panel', FilamentDashboardsPlugin::currentPanel()->getId())
            ->where('tenant_type', $tenant?->getMorphClass())
            ->where('tenant_id', $tenant?->getKey())
            ->where('user_type', $user->getMorphClass())
            ->where('user_id', $user->getKey())
            ->where('dashboard_id', $dashboard->getKey())
            ->exists();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-dashboards::dashboards.actions.set_home'))
            ->icon(Heroicon::OutlinedHome)
            ->hidden(fn (Dashboard $record): bool => self::isHome($record))
            ->action(function (Dashboard $record): void {
                app(SetHomeDashboard::class)->handle($record, DashboardResource::user(), Filament::getTenant());

                Notification::make()->success()->title(__('filament-dashboards::dashboards.notifications.home_set'))->send();
            });
    }
}

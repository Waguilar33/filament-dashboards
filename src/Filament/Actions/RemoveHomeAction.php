<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Filament\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Waguilar\FilamentDashboards\Actions\RemoveHomeDashboard;
use Waguilar\FilamentDashboards\FilamentDashboardsIcon;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class RemoveHomeAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'removeHome';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-dashboards::dashboards.actions.remove_home'))
            ->tooltip(__('filament-dashboards::dashboards.actions.remove_home_tooltip'))
            ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::HOME))
            ->visible(fn (Dashboard $record): bool => SetHomeAction::isHome($record))
            ->action(function (): void {
                app(RemoveHomeDashboard::class)->handle(DashboardResource::user(), FilamentDashboardsPlugin::currentPanel()->getId(), Filament::getTenant());

                Notification::make()->success()->title(__('filament-dashboards::dashboards.notifications.home_removed'))->send();
            });
    }
}

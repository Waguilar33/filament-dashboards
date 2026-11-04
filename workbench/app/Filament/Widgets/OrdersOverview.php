<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboard;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;

final class OrdersOverview extends StatsOverviewWidget implements DashboardWidget
{
    use InteractsWithDashboard;

    public static function dashboardDescription(): string
    {
        return 'Orders, revenue and average order value this month.';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make('Orders', '1,284'),
            Stat::make('Revenue', '$48,210'),
            Stat::make('Average order', '$37.55'),
        ];
    }
}

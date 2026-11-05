<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboard;
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboardDates;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;
use Workbench\App\Models\DemoOrder;

final class OrdersOverview extends StatsOverviewWidget implements DashboardWidget
{
    use InteractsWithDashboard;
    use InteractsWithDashboardDates;

    public static function dashboardDescription(): string
    {
        return 'Orders, revenue and average order value in the dashboard dates.';
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $range = $this->dateRange();
        $orders = DemoOrder::query()->whereBetween('ordered_at', [$range->start, $range->end]);
        $count = $orders->count();
        $revenue = (float) $orders->sum('total');

        return [
            Stat::make('Orders', Number::format($count)),
            Stat::make('Revenue', Number::currency($revenue)),
            Stat::make('Average order', Number::currency($count === 0 ? 0 : $revenue / $count)),
        ];
    }
}

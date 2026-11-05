<?php

declare(strict_types=1);

namespace Workbench\App\Dashboards;

use Waguilar\FilamentDashboards\SystemDashboards\SystemDashboard;
use Workbench\App\Filament\Widgets\OrdersOverview;
use Workbench\App\Filament\Widgets\SignupsChart;
use Workbench\App\Widgets\OrdersByChannel;
use Workbench\App\Widgets\RevenueTrend;
use Workbench\App\Widgets\SalesThisYear;

final class OverviewDashboard extends SystemDashboard
{
    public function key(): string
    {
        return 'overview';
    }

    public function name(): string
    {
        return 'Overview';
    }

    public function items(): array
    {
        return [
            ['widget' => SalesThisYear::class, 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
            ['widget' => RevenueTrend::class, 'x' => 0, 'y' => 4, 'w' => 8, 'h' => 9],
            ['widget' => OrdersByChannel::class, 'x' => 8, 'y' => 4, 'w' => 4, 'h' => 9],
            ['widget' => SignupsChart::class, 'x' => 0, 'y' => 13, 'w' => 6, 'h' => 8],
            ['widget' => OrdersOverview::class, 'x' => 6, 'y' => 13, 'w' => 6, 'h' => 4],
        ];
    }
}

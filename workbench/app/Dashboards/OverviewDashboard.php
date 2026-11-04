<?php

declare(strict_types=1);

namespace Workbench\App\Dashboards;

use Waguilar\FilamentDashboards\SystemDashboards\SystemDashboard;
use Workbench\App\Filament\Widgets\OrdersOverview;
use Workbench\App\Filament\Widgets\SignupsChart;

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
            ['widget' => OrdersOverview::class, 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2],
            ['widget' => SignupsChart::class, 'x' => 0, 'y' => 1, 'w' => 6, 'h' => 4],
        ];
    }
}

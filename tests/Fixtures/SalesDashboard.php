<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Waguilar\FilamentDashboards\SystemDashboards\SystemDashboard;
use Workbench\App\Filament\Widgets\OrdersOverview;
use Workbench\App\Filament\Widgets\SignupsChart;

final class SalesDashboard extends SystemDashboard
{
    /** @var list<array{widget: class-string<OrdersOverview|SignupsChart>, x: int, y: int, w: int, h: int}> */
    public static array $items = [];

    public function key(): string
    {
        return 'sales';
    }

    public function name(): string
    {
        return 'Sales';
    }

    public function items(): array
    {
        return self::$items;
    }
}

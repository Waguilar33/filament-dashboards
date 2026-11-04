<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\SystemDashboards;

use Waguilar\FilamentDashboards\Contracts\DashboardWidget;

abstract class SystemDashboard
{
    abstract public function key(): string;

    abstract public function name(): string;

    /**
     * @return list<array{widget: class-string<DashboardWidget>|class-string<SystemWidget>, x: int, y: int, w: int, h: int}>
     */
    abstract public function items(): array;
}

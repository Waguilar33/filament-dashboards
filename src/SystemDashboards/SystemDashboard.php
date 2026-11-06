<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\SystemDashboards;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;

abstract class SystemDashboard
{
    abstract public function key(): string;

    abstract public function name(): string;

    public function canView(Model $user, ?Model $tenant): bool
    {
        return true;
    }

    /**
     * @return list<array{widget: class-string<DashboardWidget>|class-string<SystemWidget>, x: int, y: int, w: int, h: int}>
     */
    abstract public function items(): array;
}

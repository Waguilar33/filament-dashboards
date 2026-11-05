<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Filament\Widgets\Widget;
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboard;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;

final class HiddenWidget extends Widget implements DashboardWidget
{
    use InteractsWithDashboard;

    public static function canView(): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Waguilar\FilamentDashboards\Contracts\DashboardWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;

class AddDashboardWidget
{
    /**
     * @param  class-string<DashboardWidget>  $widget
     */
    public function handle(Dashboard $dashboard, string $widget): DashboardItem
    {
        $size = $widget::dashboardSize();

        return $dashboard->items()->create([
            'widget' => $widget::dashboardKey(),
            'x' => 0,
            'y' => $dashboard->items()->count(),
            'w' => $size['w'],
            'h' => $size['h'],
        ]);
    }
}

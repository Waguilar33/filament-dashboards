<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;

class AddDashboardWidget
{
    /**
     * @param  array{w: int, h: int}  $size
     */
    public function handle(Dashboard $dashboard, string $widget, array $size): DashboardItem
    {
        return $dashboard->items()->create([
            'widget' => $widget,
            'x' => 0,
            'y' => $dashboard->items()->get(['y', 'h'])->max(fn (DashboardItem $item): int => $item->y + $item->h) ?? 0,
            'w' => $size['w'],
            'h' => $size['h'],
        ]);
    }
}

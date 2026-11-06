<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;

class AddDashboardWidget
{
    public function __construct(
        protected ShareDashboardWidgets $shareDashboardWidgets,
    ) {}

    /**
     * @param  array{w: int, h: int}  $size
     */
    public function handle(Dashboard $dashboard, string $widget, array $size): DashboardItem
    {
        $item = $dashboard->items()->create([
            'widget' => $widget,
            'x' => 0,
            'y' => $dashboard->items()->get(['y', 'h'])->max(fn (DashboardItem $item): int => $item->y + $item->h) ?? 0,
            'w' => $size['w'],
            'h' => $size['h'],
        ]);

        $this->shareDashboardWidgets->handle($dashboard, $widget);

        return $item;
    }
}

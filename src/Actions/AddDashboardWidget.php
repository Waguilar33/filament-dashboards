<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;

class AddDashboardWidget
{
    /**
     * @param  array{w: int, h: int}  $size
     */
    public function handle(Dashboard $dashboard, string $widget, array $size, Model $placedBy, bool $fitsContent = true): DashboardItem
    {
        return $dashboard->items()->create([
            'widget' => $widget,
            'x' => 0,
            'y' => $dashboard->items()->get(['y', 'h'])->max(fn (DashboardItem $item): int => $item->y + $item->h) ?? 0,
            'w' => $size['w'],
            'h' => $size['h'],
            'fits_content' => $fitsContent,
            'shares_widget' => str_starts_with($widget, BuilderWidget::KEY_PREFIX)
                && BuilderWidget::query()->whereKey(substr($widget, strlen(BuilderWidget::KEY_PREFIX)))->whereMorphedTo('owner', $placedBy)->exists(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;

class DuplicateDashboardItem
{
    public function __construct(
        protected CloneBuilderWidget $cloneBuilderWidget,
        protected AddDashboardWidget $addDashboardWidget,
    ) {}

    public function handle(Dashboard $dashboard, DashboardItem $item, ?BuilderWidget $builderWidget, Model $owner, ?Model $tenant): DashboardItem
    {
        $widget = $builderWidget === null
            ? $item->widget
            : $this->cloneBuilderWidget->handle(
                $builderWidget,
                $owner,
                $tenant,
                __('filament-dashboards::dashboards.modals.clone.name', ['name' => $builderWidget->name]),
            )->dashboardKey();

        $copy = $this->addDashboardWidget->handle($dashboard, $widget, ['w' => $item->w, 'h' => $item->h], $owner, $item->fits_content);
        $copy->update(['options' => $item->options]);

        return $copy;
    }
}

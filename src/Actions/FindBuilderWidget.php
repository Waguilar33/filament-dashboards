<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;

class FindBuilderWidget
{
    public function handle(int $id, Model $user): ?BuilderWidget
    {
        $widget = BuilderWidget::query()->find($id);

        if ($widget === null) {
            return null;
        }

        $placedOnVisibleDashboard = Dashboard::query()
            ->visibleTo($user)
            ->whereHas('items', fn ($items) => $items->where('widget', $widget->dashboardKey()))
            ->exists();

        return $placedOnVisibleDashboard || $widget->canBeViewedBy($user) ? $widget : null;
    }
}

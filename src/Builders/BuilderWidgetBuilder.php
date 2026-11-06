<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builders\Concerns\ScopesToViewer;
use Waguilar\FilamentDashboards\Models\BuilderWidget;

/**
 * @extends Builder<BuilderWidget>
 */
class BuilderWidgetBuilder extends Builder
{
    use ScopesToViewer {
        visibleTo as visibleToOwnerOrShare;
    }

    public function visibleTo(Model $user): static
    {
        return $this->where(fn (self $query): self => $query
            ->visibleToOwnerOrShare($user)
            ->orWhereHas('placements', fn (Builder $placements): Builder => $placements
                ->where('shares_widget', true)
                ->whereHas('dashboard', fn (DashboardBuilder $dashboards): DashboardBuilder => $dashboards->visibleTo($user))));
    }
}

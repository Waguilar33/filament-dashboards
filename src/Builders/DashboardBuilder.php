<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builders\Concerns\ScopesToViewer;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\Dashboard;

/**
 * @extends Builder<Dashboard>
 */
class DashboardBuilder extends Builder
{
    use ScopesToViewer;

    public function editableBy(Model $user): static
    {
        return $this
            ->whereNull('system_key')
            ->where(fn (Builder $query): Builder => $query
                ->whereMorphedTo('owner', $user)
                ->orWhereHas('shares', fn (DashboardShareBuilder $shares): DashboardShareBuilder => $shares->sharedWith($user, ShareLevel::Edit)));
    }
}

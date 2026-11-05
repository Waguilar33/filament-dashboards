<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builders\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builders\DashboardShareBuilder;

trait ScopesToViewer
{
    public function inScope(string $panel, ?Model $tenant): static
    {
        return $this
            ->where('panel', $panel)
            ->where(fn (Builder $query): Builder => $query
                ->whereNotNull('system_key')
                ->orWhere(fn (Builder $scoped): Builder => $tenant === null
                    ? $scoped->whereNull('tenant_id')
                    : $scoped->whereMorphedTo('tenant', $tenant)));
    }

    public function visibleTo(Model $user): static
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->whereNotNull('system_key')
            ->orWhereMorphedTo('owner', $user)
            ->orWhereHas('shares', fn (DashboardShareBuilder $shares): DashboardShareBuilder => $shares->sharedWith($user)));
    }
}

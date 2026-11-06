<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builders\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builders\DashboardShareBuilder;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;

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
        $panel = Filament::getCurrentOrDefaultPanel();
        $hidden = $panel === null ? [] : (FilamentDashboardsPlugin::forPanel($panel->getId())?->getHiddenSystemKeys($this->systemKind(), $user, Filament::getTenant()) ?? []);

        return $this->where(fn (Builder $query): Builder => $query
            ->where(fn (Builder $system): Builder => $system->whereNotNull('system_key')->whereNotIn('system_key', $hidden))
            ->orWhereMorphedTo('owner', $user)
            ->orWhereHas('shares', fn (DashboardShareBuilder $shares): DashboardShareBuilder => $shares->sharedWith($user)));
    }

    protected function systemKind(): string
    {
        return $this->getModel() instanceof BuilderWidget ? 'widgets' : 'dashboards';
    }
}

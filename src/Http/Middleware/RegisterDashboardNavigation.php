<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Waguilar\FilamentDashboards\Enums\NavigationPosition;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class RegisterDashboardNavigation
{
    protected const RUNS_AFTER_TENANT = false;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $panel = FilamentDashboardsPlugin::currentPanel();
        $user = Filament::auth()->user();
        $tenant = Filament::getTenant();

        if (! $user instanceof Model || $panel->hasTenancy() !== static::RUNS_AFTER_TENANT || ! $this->rendersNavigation($request, $panel)) {
            return $next($request);
        }

        $groups = FilamentDashboardsPlugin::get()->getNavigationGroups($panel);

        $dashboards = Dashboard::query()
            ->inScope($panel->getId(), $tenant)
            ->visibleTo($user)
            ->where('show_in_navigation', true)
            ->get()
            ->sortBy(fn (Dashboard $dashboard): string => mb_strtolower(filled($dashboard->navigation_label) ? $dashboard->navigation_label : $dashboard->name))
            ->values();

        $panel->navigationItems($dashboards
            ->map(fn (Dashboard $dashboard): ?NavigationItem => FilamentDashboardsPlugin::get()->modifyDashboardNavigationItem(NavigationItem::make(filled($dashboard->navigation_label) ? $dashboard->navigation_label : $dashboard->name)
                ->url(DashboardResource::getUrl('view', ['record' => $dashboard]))
                ->icon($dashboard->navigation_icon)
                ->group($dashboard->navigation_group === null ? null : ($groups[$dashboard->navigation_group] ?? $dashboard->navigation_group))
                ->sort($dashboard->navigation_position === NavigationPosition::Start ? -1000 : 1000)
                ->isActiveWhen(fn (): bool => $request->routeIs(DashboardResource::getRouteBaseName().'.view')
                    && $request->route('record') === $dashboard->slug), $dashboard))
            ->filter()
            ->all());

        return $next($request);
    }

    protected function rendersNavigation(Request $request, Panel $panel): bool
    {
        if (! Livewire::isLivewireRequest()) {
            return true;
        }

        $finder = app('livewire.finder');
        $navigation = collect([$panel->getSidebarLivewireComponent(), $panel->getTopbarLivewireComponent()])
            ->flatMap(fn (string $component): array => [$component, $finder->normalizeName($component)])
            ->all();

        return collect(Arr::wrap($request->input('components')))->contains(function (mixed $component) use ($navigation): bool {
            $snapshot = is_array($component) && is_string($component['snapshot'] ?? null) ? json_decode($component['snapshot'], true) : null;

            return in_array(data_get($snapshot, 'memo.name'), $navigation, true);
        });
    }
}

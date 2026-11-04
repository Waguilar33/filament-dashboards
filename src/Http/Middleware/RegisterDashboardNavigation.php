<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Waguilar\FilamentDashboards\Enums\NavigationPosition;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class RegisterDashboardNavigation
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $panel = FilamentDashboardsPlugin::currentPanel();
        $user = Filament::auth()->user();
        $tenant = Filament::getTenant();

        if (! $user instanceof Model || ($panel->hasTenancy() && $tenant === null)) {
            return $next($request);
        }

        $panel->navigationItems(Dashboard::query()
            ->inScope($panel->getId(), $tenant)
            ->visibleTo($user)
            ->where('show_in_navigation', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Dashboard $dashboard): NavigationItem => NavigationItem::make(filled($dashboard->navigation_label) ? $dashboard->navigation_label : $dashboard->name)
                ->url(DashboardResource::getUrl('view', ['record' => $dashboard]))
                ->icon($dashboard->navigation_icon)
                ->group($dashboard->navigation_group)
                ->sort($dashboard->navigation_position === NavigationPosition::Start ? -1000 : 1000)
                ->isActiveWhen(fn (): bool => $request->routeIs(DashboardResource::getRouteBaseName().'.view')
                    && $request->route('record') === $dashboard->slug))
            ->all());

        return $next($request);
    }
}

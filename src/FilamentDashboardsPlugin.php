<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\SystemDashboards\SystemDashboard;

final class FilamentDashboardsPlugin implements Plugin
{
    /**
     * @var (Closure(Model, class-string<DashboardWidget>): bool)|null
     */
    protected ?Closure $authorizeWidgetsUsing = null;

    /**
     * @var (Closure(Model): bool)|null
     */
    protected ?Closure $canCreateDashboardsUsing = null;

    /**
     * @var list<class-string<SystemDashboard>>
     */
    protected array $systemDashboards = [];

    public static function make(): static
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return FilamentDashboardsServiceProvider::NAME;
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * @param  (Closure(Model, class-string<DashboardWidget>): bool)|null  $callback
     */
    public function authorizeWidgetsUsing(?Closure $callback): static
    {
        $this->authorizeWidgetsUsing = $callback;

        return $this;
    }

    /**
     * @param  (Closure(Model): bool)|null  $callback
     */
    public function canCreateDashboardsUsing(?Closure $callback): static
    {
        $this->canCreateDashboardsUsing = $callback;

        return $this;
    }

    /**
     * @param  list<class-string<SystemDashboard>>  $dashboards
     */
    public function systemDashboards(array $dashboards): static
    {
        $this->systemDashboards = $dashboards;

        return $this;
    }

    /**
     * @return list<class-string<SystemDashboard>>
     */
    public function getSystemDashboards(): array
    {
        return $this->systemDashboards;
    }

    /**
     * @return array<string, class-string<Widget&DashboardWidget>>
     */
    public function getDashboardWidgets(Panel $panel): array
    {
        $widgets = [];

        foreach ($panel->getWidgets() as $widget) {
            $class = $widget instanceof WidgetConfiguration ? $widget->widget : $widget;

            if (is_subclass_of($class, DashboardWidget::class)) {
                $widgets[$class::dashboardKey()] = $class;
            }
        }

        return $widgets;
    }

    /**
     * @return array<string, class-string<Widget&DashboardWidget>>
     */
    public function getAvailableWidgets(Panel $panel, Model $user): array
    {
        return array_filter(
            $this->getDashboardWidgets($panel),
            fn (string $widget): bool => $widget::canView()
                && ($this->authorizeWidgetsUsing === null || ($this->authorizeWidgetsUsing)($user, $widget)),
        );
    }

    public function canCreateDashboards(Model $user): bool
    {
        if (Gate::getPolicyFor(Dashboard::class) !== null) {
            return Gate::forUser($user)->allows('create', Dashboard::class);
        }

        return $this->canCreateDashboardsUsing === null || ($this->canCreateDashboardsUsing)($user);
    }
}

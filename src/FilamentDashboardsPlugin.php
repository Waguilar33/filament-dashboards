<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;
use Waguilar\FilamentDashboards\Http\Middleware\RegisterDashboardNavigation;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\BuilderWidgetResource;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;
use Waguilar\FilamentDashboards\SystemDashboards\SystemDashboard;
use Waguilar\FilamentDashboards\SystemDashboards\SystemWidget;

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

    /**
     * @var list<class-string<SystemWidget>>
     */
    protected array $systemWidgets = [];

    /**
     * @var list<class-string<DataSource>>
     */
    protected array $dataSources = [];

    /**
     * @var (Closure(?Model): Builder<Model>)|null
     */
    protected ?Closure $shareableUsersUsing = null;

    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static */
        return self::currentPanel()->getPlugin(FilamentDashboardsServiceProvider::NAME);
    }

    public static function currentPanel(): Panel
    {
        return Filament::getCurrentOrDefaultPanel() ?? throw new LogicException('Filament Dashboards needs a Filament panel.');
    }

    public function getId(): string
    {
        return FilamentDashboardsServiceProvider::NAME;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([DashboardResource::class, BuilderWidgetResource::class])
            ->authMiddleware([RegisterDashboardNavigation::class], isPersistent: true)
            ->tenantMiddleware([RegisterDashboardNavigation::class], isPersistent: true);
    }

    public function boot(Panel $panel): void {}

    /**
     * @param  (Closure(?Model): Builder<Model>)|null  $callback
     */
    public function shareableUsersUsing(?Closure $callback): static
    {
        $this->shareableUsersUsing = $callback;

        return $this;
    }

    /**
     * @return Builder<Model>
     */
    public function getShareableUsers(?Model $tenant): Builder
    {
        if ($this->shareableUsersUsing !== null) {
            return ($this->shareableUsersUsing)($tenant);
        }

        return $this->newUser()->newQuery();
    }

    /**
     * @return BelongsToMany<Model, Model>|null
     */
    public function getRolesRelationship(): ?BelongsToMany
    {
        $user = $this->newUser();
        $roles = method_exists($user, 'roles') ? $user->roles() : null;

        return $roles instanceof BelongsToMany ? $roles : null;
    }

    /**
     * @return Builder<Model>|null
     */
    public function getShareableRoles(): ?Builder
    {
        $query = $this->getRolesRelationship()?->getRelated()->newQuery();

        if ($query !== null && config('permission.teams') === true && function_exists('getPermissionsTeamId')) {
            $team = config()->string('permission.column_names.team_foreign_key');
            $query->where(fn (Builder $roles): Builder => $roles->whereNull($team)->orWhere($team, getPermissionsTeamId()));
        }

        return $query;
    }

    protected function newUser(): Model
    {
        $guard = Filament::auth();
        $provider = method_exists($guard, 'getProvider') ? $guard->getProvider() : null;
        /** @var class-string<Model> $model */
        $model = $provider instanceof EloquentUserProvider ? $provider->getModel() : config('auth.providers.users.model');

        return new $model;
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
     * @param  list<class-string<DataSource>>  $sources
     */
    public function dataSources(array $sources): static
    {
        $this->dataSources = $sources;

        return $this;
    }

    /**
     * @return array<string, DataSource>
     */
    public function getDataSources(): array
    {
        $sources = [];

        foreach ($this->dataSources as $class) {
            $source = new $class;
            $sources[$source->key()] = $source;
        }

        return $sources;
    }

    /**
     * @param  list<class-string<SystemWidget>>  $widgets
     */
    public function systemWidgets(array $widgets): static
    {
        $this->systemWidgets = $widgets;

        return $this;
    }

    /**
     * @return list<class-string<SystemWidget>>
     */
    public function getSystemWidgets(): array
    {
        return $this->systemWidgets;
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

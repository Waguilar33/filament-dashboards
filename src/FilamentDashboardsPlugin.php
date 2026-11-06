<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;
use Waguilar\FilamentDashboards\Filament\Actions\ShareAction;
use Waguilar\FilamentDashboards\Http\Middleware\RegisterDashboardNavigation;
use Waguilar\FilamentDashboards\Http\Middleware\RegisterTenantDashboardNavigation;
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
     * @var (Closure(?Model): Builder<covariant Model>)|null
     */
    protected ?Closure $shareableUsersUsing = null;

    /**
     * @var (Closure(array<string, Action>): array<Action|ActionGroup>)|null
     */
    protected ?Closure $headerActionsUsing = null;

    /**
     * @var (Closure(Table, array<string, Action>): Table)|null
     */
    protected ?Closure $modifyDashboardsTableUsing = null;

    /**
     * @var (Closure(Table, array<string, Action>): Table)|null
     */
    protected ?Closure $modifyWidgetsTableUsing = null;

    /**
     * @var list<int>
     */
    protected array $datePresets = [1, 15, 30, 60, 90];

    protected int $defaultDatePreset = 30;

    /**
     * @var (Closure(): string)|null
     */
    protected ?Closure $timezoneUsing = null;

    /**
     * @var (Closure(string): Field)|null
     */
    protected ?Closure $navigationIconFieldUsing = null;

    /**
     * @var (Closure(): array<string, string>)|null
     */
    protected ?Closure $navigationGroupsUsing = null;

    /**
     * @var (Closure(Schema): Schema)|null
     */
    protected ?Closure $modifyDashboardFormUsing = null;

    /**
     * @var (Closure(CreateAction): CreateAction)|null
     */
    protected ?Closure $modifyCreateDashboardActionUsing = null;

    /**
     * @var (Closure(EditAction): EditAction)|null
     */
    protected ?Closure $modifySettingsActionUsing = null;

    /**
     * @var (Closure(ShareAction): ShareAction)|null
     */
    protected ?Closure $modifyShareActionUsing = null;

    /**
     * @var (Closure(Action): Action)|null
     */
    protected ?Closure $modifyAddWidgetActionUsing = null;

    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static */
        return self::currentPanel()->getPlugin(FilamentDashboardsServiceProvider::NAME);
    }

    public static function forPanel(string $panel): ?static
    {
        $instance = Filament::getPanel($panel, isStrict: false);

        if ($instance === null || ! $instance->hasPlugin(FilamentDashboardsServiceProvider::NAME)) {
            return null;
        }

        /** @var static */
        return $instance->getPlugin(FilamentDashboardsServiceProvider::NAME);
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
            ->tenantMiddleware([RegisterTenantDashboardNavigation::class], isPersistent: true);
    }

    public function boot(Panel $panel): void {}

    /**
     * @param  (Closure(?Model): Builder<covariant Model>)|null  $callback
     */
    public function shareableUsersUsing(?Closure $callback): static
    {
        $this->shareableUsersUsing = $callback;

        return $this;
    }

    /**
     * @return Builder<covariant Model>
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
     * @return array<string, DataSource>
     */
    public function getDataSourcesFor(Model $user, ?Model $tenant): array
    {
        return array_filter($this->getDataSources(), fn (DataSource $source): bool => $source->canView($user, $tenant));
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

    public function getSystemDashboardName(string $key): ?string
    {
        foreach ($this->systemDashboards as $class) {
            $dashboard = new $class;

            if ($dashboard->key() === $key) {
                return $dashboard->name();
            }
        }

        return null;
    }

    public function getSystemWidgetName(string $key): ?string
    {
        foreach ($this->systemWidgets as $class) {
            $widget = new $class;

            if ($widget->key() === $key) {
                return $widget->name();
            }
        }

        return null;
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

    /**
     * @param  (Closure(Schema): Schema)|null  $callback
     */
    public function modifyDashboardFormUsing(?Closure $callback): static
    {
        $this->modifyDashboardFormUsing = $callback;

        return $this;
    }

    public function modifyDashboardForm(Schema $schema): Schema
    {
        return $this->modifyDashboardFormUsing === null ? $schema : ($this->modifyDashboardFormUsing)($schema);
    }

    /**
     * @param  (Closure(CreateAction): CreateAction)|null  $callback
     */
    public function modifyCreateDashboardActionUsing(?Closure $callback): static
    {
        $this->modifyCreateDashboardActionUsing = $callback;

        return $this;
    }

    public function modifyCreateDashboardAction(CreateAction $action): CreateAction
    {
        return $this->modifyCreateDashboardActionUsing === null ? $action : ($this->modifyCreateDashboardActionUsing)($action);
    }

    /**
     * @param  (Closure(EditAction): EditAction)|null  $callback
     */
    public function modifySettingsActionUsing(?Closure $callback): static
    {
        $this->modifySettingsActionUsing = $callback;

        return $this;
    }

    public function modifySettingsAction(EditAction $action): EditAction
    {
        return $this->modifySettingsActionUsing === null ? $action : ($this->modifySettingsActionUsing)($action);
    }

    /**
     * @param  (Closure(ShareAction): ShareAction)|null  $callback
     */
    public function modifyShareActionUsing(?Closure $callback): static
    {
        $this->modifyShareActionUsing = $callback;

        return $this;
    }

    public function modifyShareAction(ShareAction $action): ShareAction
    {
        return $this->modifyShareActionUsing === null ? $action : ($this->modifyShareActionUsing)($action);
    }

    /**
     * @param  (Closure(Action): Action)|null  $callback
     */
    public function modifyAddWidgetActionUsing(?Closure $callback): static
    {
        $this->modifyAddWidgetActionUsing = $callback;

        return $this;
    }

    public function modifyAddWidgetAction(Action $action): Action
    {
        return $this->modifyAddWidgetActionUsing === null ? $action : ($this->modifyAddWidgetActionUsing)($action);
    }

    /**
     * @param  (Closure(string): Field)|null  $callback
     */
    public function navigationIconFieldUsing(?Closure $callback): static
    {
        $this->navigationIconFieldUsing = $callback;

        return $this;
    }

    public function getNavigationIconField(string $name): ?Field
    {
        return $this->navigationIconFieldUsing === null ? null : ($this->navigationIconFieldUsing)($name);
    }

    /**
     * @param  (Closure(): array<string, string>)|null  $callback
     */
    public function navigationGroupsUsing(?Closure $callback): static
    {
        $this->navigationGroupsUsing = $callback;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getNavigationGroups(Panel $panel): array
    {
        if ($this->navigationGroupsUsing !== null) {
            return ($this->navigationGroupsUsing)();
        }

        $groups = [];

        foreach ($panel->getNavigationGroups() as $index => $group) {
            $label = $group instanceof NavigationGroup ? $group->getLabel() : $group;
            $label = $label instanceof Htmlable ? $label->toHtml() : $label;

            if (filled($label)) {
                $groups[is_string($index) ? $index : $label] = $label;
            }
        }

        return $groups;
    }

    /**
     * @param  list<int>  $days
     */
    public function datePresets(array $days): static
    {
        $this->datePresets = $days;

        return $this;
    }

    /**
     * @return list<int>
     */
    public function getDatePresets(): array
    {
        return $this->datePresets;
    }

    public function defaultDatePreset(int $days): static
    {
        $this->defaultDatePreset = $days;

        return $this;
    }

    public function getDefaultDatePreset(): int
    {
        return $this->defaultDatePreset;
    }

    /**
     * @param  (Closure(): string)|null  $callback
     */
    public function timezoneUsing(?Closure $callback): static
    {
        $this->timezoneUsing = $callback;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezoneUsing === null ? Config::string('app.timezone') : ($this->timezoneUsing)();
    }

    /**
     * @param  (Closure(array<string, Action>): array<Action|ActionGroup>)|null  $callback
     */
    public function headerActionsUsing(?Closure $callback): static
    {
        $this->headerActionsUsing = $callback;

        return $this;
    }

    /**
     * @param  array<string, Action>  $actions
     * @return array<Action|ActionGroup>
     */
    public function arrangeHeaderActions(array $actions): array
    {
        if ($this->headerActionsUsing !== null) {
            return ($this->headerActionsUsing)($actions);
        }

        return [
            $actions['edit'],
            $actions['share'],
            ActionGroup::make([
                $actions['settings'],
                $actions['print'],
                $actions['duplicate'],
                $actions['setAsHome'],
                $actions['removeHome'],
                $actions['delete'],
            ]),
        ];
    }

    /**
     * @param  (Closure(Table, array<string, Action>): Table)|null  $callback
     */
    public function modifyDashboardsTableUsing(?Closure $callback): static
    {
        $this->modifyDashboardsTableUsing = $callback;

        return $this;
    }

    /**
     * @param  array<string, Action>  $actions
     */
    public function modifyDashboardsTable(Table $table, array $actions): Table
    {
        return $this->modifyDashboardsTableUsing === null ? $table : ($this->modifyDashboardsTableUsing)($table, $actions);
    }

    /**
     * @param  (Closure(Table, array<string, Action>): Table)|null  $callback
     */
    public function modifyWidgetsTableUsing(?Closure $callback): static
    {
        $this->modifyWidgetsTableUsing = $callback;

        return $this;
    }

    /**
     * @param  array<string, Action>  $actions
     */
    public function modifyWidgetsTable(Table $table, array $actions): Table
    {
        return $this->modifyWidgetsTableUsing === null ? $table : ($this->modifyWidgetsTableUsing)($table, $actions);
    }
}

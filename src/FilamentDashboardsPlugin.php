<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards;

use Carbon\CarbonInterface;
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
use Filament\Schemas\Components\Component;
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
use Illuminate\Support\Str;
use LogicException;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;
use Waguilar\FilamentDashboards\Dates\DatePeriod;
use Waguilar\FilamentDashboards\Dates\Periods\LastMonth;
use Waguilar\FilamentDashboards\Dates\Periods\LastThirtyDays;
use Waguilar\FilamentDashboards\Dates\Periods\ThisMonth;
use Waguilar\FilamentDashboards\Dates\Periods\ThisQuarter;
use Waguilar\FilamentDashboards\Dates\Periods\ThisWeek;
use Waguilar\FilamentDashboards\Dates\Periods\ThisYear;
use Waguilar\FilamentDashboards\Dates\Periods\Today;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Exports\CsvExporter;
use Waguilar\FilamentDashboards\Exports\WidgetExporter;
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
     * @var list<int|string>
     */
    protected array $datePresets = [1, 15, 30, 60, 90];

    protected int|string $defaultDatePreset = 30;

    /**
     * @var list<class-string<DatePeriod>|DatePeriod>
     */
    protected array $datePeriods = [Today::class, ThisWeek::class, ThisMonth::class, LastMonth::class, ThisQuarter::class, ThisYear::class, LastThirtyDays::class];

    protected int $weekStart = CarbonInterface::MONDAY;

    /**
     * @var list<BuilderWidgetType>|null
     */
    protected ?array $builderWidgetTypes = null;

    /**
     * @var (Closure(array<Component>, BuilderWidgetType): array<Component>)|null
     */
    protected ?Closure $modifyBuilderWidgetFieldsUsing = null;

    /**
     * @var array<string, int>
     */
    protected array $builderLimits = ['table' => 100, 'summary' => 100, 'breakdown' => 20, 'scatter' => 500, 'drill_down' => 100];

    protected ?int $minRefreshInterval = 5;

    /**
     * @var list<WidgetExporter>|null
     */
    protected ?array $exporters = null;

    protected string $csvDelimiter = ',';

    /**
     * @var (Closure(string): string)|null
     */
    protected ?Closure $exportFileNameUsing = null;

    /**
     * @var (Closure(): string)|null
     */
    protected ?Closure $timezoneUsing = null;

    protected bool $hasScheduledNotifications = false;

    /**
     * @var (Closure(int|float, int): string)|null
     */
    protected ?Closure $formatNumbersUsing = null;

    /**
     * @var (Closure(CarbonInterface, string): string)|null
     */
    protected ?Closure $formatDatesUsing = null;

    /**
     * @var (Closure(Model, ?Model, Closure(): mixed): mixed)|null
     */
    protected ?Closure $recipientContextUsing = null;

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

    /**
     * @var (Closure(Action, string): Action)|null
     */
    protected ?Closure $modifyActionUsing = null;

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
     * @param  (Closure(Action, string): Action)|null  $callback
     */
    public function modifyActionUsing(?Closure $callback): static
    {
        $this->modifyActionUsing = $callback;

        return $this;
    }

    /**
     * @template TAction of Action
     *
     * @param  TAction  $action
     * @return TAction
     */
    public function modifyAction(Action $action, string $name): Action
    {
        if ($this->modifyActionUsing === null) {
            return $action;
        }

        /** @var TAction */
        return ($this->modifyActionUsing)($action, $name);
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
     * @param  list<int|string>  $presets
     */
    public function datePresets(array $presets): static
    {
        $this->datePresets = $presets;

        return $this;
    }

    /**
     * @return list<int|string>
     */
    public function getDatePresets(): array
    {
        return array_values(array_filter($this->datePresets, fn (int|string $preset): bool => is_int($preset) || $this->getDatePeriod($preset) !== null));
    }

    public function defaultDatePreset(int|string $preset): static
    {
        $this->defaultDatePreset = $preset;

        return $this;
    }

    public function getDefaultDatePreset(): int|string
    {
        return $this->defaultDatePreset;
    }

    /**
     * @param  list<class-string<DatePeriod>|DatePeriod>  $periods
     */
    public function datePeriods(array $periods): static
    {
        $this->datePeriods = [...$this->datePeriods, ...$periods];

        return $this;
    }

    /**
     * @return array<string, DatePeriod>
     */
    public function getDatePeriods(): array
    {
        $periods = [];

        foreach ($this->datePeriods as $period) {
            $period = $period instanceof DatePeriod ? $period : new $period;
            $periods[$period->key()] = $period;
        }

        return $periods;
    }

    public function getDatePeriod(string $key): ?DatePeriod
    {
        return $this->getDatePeriods()[$key] ?? null;
    }

    /**
     * @param  list<BuilderWidgetType>  $types
     */
    public function builderWidgetTypes(array $types): static
    {
        $this->builderWidgetTypes = $types;

        return $this;
    }

    /**
     * @return list<BuilderWidgetType>
     */
    public function getBuilderWidgetTypes(): array
    {
        return $this->builderWidgetTypes ?? BuilderWidgetType::cases();
    }

    /**
     * @param  (Closure(array<Component>, BuilderWidgetType): array<Component>)|null  $callback
     */
    public function modifyBuilderWidgetFieldsUsing(?Closure $callback): static
    {
        $this->modifyBuilderWidgetFieldsUsing = $callback;

        return $this;
    }

    /**
     * @param  array<Component>  $fields
     * @return array<Component>
     */
    public function modifyBuilderWidgetFields(array $fields, BuilderWidgetType $type): array
    {
        return $this->modifyBuilderWidgetFieldsUsing === null ? $fields : ($this->modifyBuilderWidgetFieldsUsing)($fields, $type);
    }

    /**
     * @param  array<string, int>  $limits
     */
    public function builderLimits(array $limits): static
    {
        $this->builderLimits = [...$this->builderLimits, ...$limits];

        return $this;
    }

    public function getBuilderLimit(string $key): int
    {
        return $this->builderLimits[$key];
    }

    /**
     * @param  list<WidgetExporter>  $exporters
     */
    public function exporters(array $exporters): static
    {
        $this->exporters = $exporters;

        return $this;
    }

    /**
     * @return list<WidgetExporter>
     */
    public function getExporters(): array
    {
        return $this->exporters ?? [new CsvExporter($this->csvDelimiter)];
    }

    public function csvDelimiter(string $delimiter): static
    {
        $this->csvDelimiter = $delimiter;

        return $this;
    }

    /**
     * @param  (Closure(string): string)|null  $callback
     */
    public function exportFileNameUsing(?Closure $callback): static
    {
        $this->exportFileNameUsing = $callback;

        return $this;
    }

    public function exportFileName(string $widgetName): string
    {
        return $this->exportFileNameUsing === null
            ? Str::slug($widgetName).'-'.now()->format('Y-m-d')
            : ($this->exportFileNameUsing)($widgetName);
    }

    public function minRefreshInterval(?int $seconds): static
    {
        $this->minRefreshInterval = $seconds;

        return $this;
    }

    public function getMinRefreshInterval(): ?int
    {
        return $this->minRefreshInterval;
    }

    public function weekStartsOn(int $day): static
    {
        $this->weekStart = $day;

        return $this;
    }

    public function getWeekStart(): int
    {
        return $this->weekStart;
    }

    /**
     * @param  (Closure(): string)|null  $callback
     */
    public function timezoneUsing(?Closure $callback): static
    {
        $this->timezoneUsing = $callback;

        return $this;
    }

    /**
     * @param  (Closure(int|float, int): string)|null  $callback
     */
    public function formatNumbersUsing(?Closure $callback): static
    {
        $this->formatNumbersUsing = $callback;

        return $this;
    }

    public function formatNumber(int|float $value, ?int $decimals = null): string
    {
        $decimals ??= is_int($value) || floor($value) === $value ? 0 : 2;

        return $this->formatNumbersUsing === null ? number_format($value, $decimals) : ($this->formatNumbersUsing)($value, $decimals);
    }

    /**
     * @param  (Closure(CarbonInterface, string): string)|null  $callback
     */
    public function formatDatesUsing(?Closure $callback): static
    {
        $this->formatDatesUsing = $callback;

        return $this;
    }

    public function formatDate(CarbonInterface $date, string $context): string
    {
        if ($this->formatDatesUsing !== null) {
            return ($this->formatDatesUsing)($date, $context);
        }

        return $date->translatedFormat(match ($context) {
            'day', 'week' => 'M j',
            'month' => 'M Y',
            'year' => 'Y',
            default => 'j M Y',
        });
    }

    public function scheduledNotifications(bool $condition = true): static
    {
        $this->hasScheduledNotifications = $condition;

        return $this;
    }

    public function hasScheduledNotifications(): bool
    {
        return $this->hasScheduledNotifications;
    }

    /**
     * @param  (Closure(Model, ?Model, Closure(): mixed): mixed)|null  $callback
     */
    public function recipientContextUsing(?Closure $callback): static
    {
        $this->recipientContextUsing = $callback;

        return $this;
    }

    /**
     * @param  Closure(): mixed  $next
     */
    public function inRecipientContext(Model $user, ?Model $tenant, Closure $next): mixed
    {
        return $this->recipientContextUsing === null ? $next() : ($this->recipientContextUsing)($user, $tenant, $next);
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
                $actions['emailMe'],
                $actions['alerts'],
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

# Filament Dashboards

Users build their own dashboards from your panel's widgets, share them with other users or roles, clone them, and pick one as their home page. You can ship read-only system dashboards everyone can clone.

Beta. The rules behind every behaviour are in [docs/architecture](docs/architecture/README.md).

## Install

```bash
composer require waguilar33/filament-dashboards:@beta
php artisan filament-dashboards:install
```

When your users or tenants have uuid or ulid keys, set them in `config/filament-dashboards.php` before migrating:

```php
'key_types' => [
    'users' => 'uuid',
    'tenants' => 'uuid',
],
```

Left empty, they follow Laravel's default morph key type. Roles shared with must use the same key type as users.

Register the plugin on a panel, and swap Filament's `Dashboard` page for the plugin's `Home` page so users can pick a home dashboard:

```php
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Pages\Home;

$panel
    ->pages([Home::class])
    ->plugin(FilamentDashboardsPlugin::make());
```

## Dashboard widgets

Any widget registered on the panel (discovered or listed) becomes available to dashboards when it implements `DashboardWidget`. The trait gives every method a default:

```php
use Filament\Widgets\StatsOverviewWidget;
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboard;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;

class OrdersOverview extends StatsOverviewWidget implements DashboardWidget
{
    use InteractsWithDashboard;

    public static function dashboardDescription(): ?string
    {
        return 'Orders, revenue and average order value this month.';
    }
}
```

A widget is offered to a user when its `canView()` passes. Dashboard widgets stay off the `Home` page's default widgets.

## System dashboards

Define them in code and register them on the plugin:

```php
use Waguilar\FilamentDashboards\SystemDashboards\SystemDashboard;

class OverviewDashboard extends SystemDashboard
{
    public function key(): string { return 'overview'; }

    public function name(): string { return 'Overview'; }

    public function items(): array
    {
        return [
            ['widget' => OrdersOverview::class, 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2],
        ];
    }
}

FilamentDashboardsPlugin::make()->systemDashboards([OverviewDashboard::class]);
```

Run `php artisan dashboards:sync` on every deploy. Names come from `name()` in the viewer's language, so it can return a translation. Everyone can view system dashboards and clone them; nobody can edit or delete them.

## The widget builder

Register the data your users may build widgets from. Each data source returns its own query, already limited to what that user and tenant may see; the builder never reaches any other model or column.

```php
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Field;

class OrdersSource extends DataSource
{
    public function key(): string { return 'orders'; }

    public function label(): string { return 'Orders'; }

    public function group(): ?string { return 'Shop'; }

    public function query(Model $user, ?Model $tenant): Builder
    {
        return Order::query()->whereBelongsTo($tenant);
    }

    public function fields(): array
    {
        return [
            Field::choice('status', 'Status', ['paid' => 'Paid', 'pending' => 'Pending']),
            Field::number('total', 'Total'),
            Field::boolean('paid', 'Paid'),
            Field::date('ordered_at', 'Ordered at'),
        ];
    }
}

FilamentDashboardsPlugin::make()->dataSources([OrdersSource::class]);
```

A field can read through the source model's relationships with dot notation, as a Filament column does: `Field::text('product.family.name', 'Family')` joins `product` and its `family` for grouping, filters, sorting and figures. Each step must be a belongs-to or has-one relationship; a has-many or belongs-to-many step is refused, because grouping by it would count a row once per related record. The joins ignore the related models' own scopes (such as soft deletes), so filter those in `query()` when they matter.

Let people click a stats card or summary row to see the records behind it by listing the fields they may see: `public function drillDownFields(Model $user, ?Model $tenant): array { return ['number', 'customer', 'total']; }` (none by default). A `date` column with no time is compared by its date when its field is marked `->dateOnly()`. A number stored scaled, such as cents, is shown in its real units with `Field::number('total', 'Total')->divideBy(100)`; filters on it are compared in those units too. Format how its values read on cards and in tables with `->formatUsing(fn (int|float $value): string => Number::currency($value, 'GTQ'))`.

Hide a source from users who may not use it; they are not offered it in the builder, and widgets built on it are left off their dashboards:

```php
public function canView(Model $user, ?Model $tenant): bool
{
    return $user->can('viewAny', Order::class);
}
```

Users then pick New widget (stats cards, table, summary table, figures table, line, bar, scatter, pie, doughnut, polar area), choose a source, a metric and filters, and see a preview before saving. In edit mode, drag the side handle of a stats card to size it on a 12-column row; sizes are kept per dashboard placement, and cards stack on widgets narrower than 384px. A stats card with a dashboard date field can pin its own period (today, this week, this month, this year, last 30 days), compare with the period before, and show progress towards a goal. Stats cards show without a heading unless *Show title* is on, which groups them in a section headed by the widget name. A summary table groups by one field and shows one or more figures per group (count, or sum, average, min or max of a number), each with an optional share of the total, and an optional total row. Saved widgets appear under Widgets, where they can be shared, cloned and deleted like dashboards, and History lists who saved each version and restores an earlier one; Create there builds one with the same picker and form, without placing it on a dashboard. Ship ready-made ones as system widgets:

```php
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\SystemDashboards\SystemWidget;

class OrdersByChannel extends SystemWidget
{
    public function key(): string { return 'orders-by-channel'; }

    public function name(): string { return 'Orders by channel'; }

    public function type(): BuilderWidgetType { return BuilderWidgetType::Doughnut; }

    public function config(): array
    {
        return ['source' => 'orders', 'group_field' => 'channel', 'metric' => 'count'];
    }
}

FilamentDashboardsPlugin::make()->systemWidgets([OrdersByChannel::class]);
```

A system dashboard can place a system widget by listing its class in `items()`.

A figures table lays figures out like a spreadsheet. Each column is a figure (its own source, metric and filters) or a calculation on the columns before it (+, −, ×, ÷ with another column or a number). The first row shows the figures, and each further row applies one operation to them, such as ÷ 1.12 for amounts without VAT; a column can be left blank in some rows. A column can also show its share of another column above its label. Dividing by zero leaves the cell empty. Table, summary and figures widgets have an Export button that downloads what is shown as a CSV file. Choose the delimiter and file name, add your own formats by extending `WidgetExporter` (several formats turn Export into a menu), and refuse export for a source with `canExport($user, $tenant)`:

```php
FilamentDashboardsPlugin::make()
    ->csvDelimiter(';')
    ->exportFileNameUsing(fn (string $widgetName): string => Str::slug($widgetName))
    ->exporters([new CsvExporter(';'), new XlsxExporter]);
```

Limit the builder to the widget types you want, change the fields a type shows, cap rows and set the shortest auto-refresh (`null` turns auto-refresh off):

```php
FilamentDashboardsPlugin::make()
    ->builderWidgetTypes([BuilderWidgetType::Stats, BuilderWidgetType::Table, BuilderWidgetType::Bar])
    ->modifyBuilderWidgetFieldsUsing(fn (array $fields, BuilderWidgetType $type): array => $fields)
    ->builderLimits(['table' => 50, 'summary' => 100, 'breakdown' => 20, 'scatter' => 500, 'drill_down' => 100])
    ->minRefreshInterval(60);
```

Pick the paper size for Print, the chart colors and options, how many versions History keeps, and mark fields where a drop is good news:

```php
FilamentDashboardsPlugin::make()
    ->printPageSize('A4')
    ->chartColors(['#0ea5e9', '#22c55e', '#f43f5e'])
    ->modifyChartOptionsUsing(fn (array $options, string $type): array => $options)
    ->widgetVersionsToKeep(50);

Field::number('refunds', 'Refunds')->lowerIsBetter();
```

## Dashboard dates

A widget that reads Filament's page filters makes the dashboard show a date bar: presets (today, 15, 30, 60 and 90 days, each counted back from today, today included) and Custom, which shows a start and end date. Every such widget on the dashboard follows it; each viewer's choice is kept in their session. Use the plugin's trait to get the range:

```php
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboardDates;

class SalesByCategory extends TableWidget implements DashboardWidget
{
    use InteractsWithDashboard;
    use InteractsWithDashboardDates;

    public function table(Table $table): Table
    {
        $range = $this->dateRange();

        return $table->query(Sale::query()->whereBetween('sold_at', [$range->start, $range->end]));
    }
}
```

`dateRange()` returns whole days in the app's time zone (`start` at 00:00:00, `end` at 23:59:59, both `CarbonImmutable`); on a page without the date bar it returns the default preset. Builder widgets follow the dates too: pick a *Dashboard date field* (stats cards, tables, breakdown and scatter charts), or the *Dashboard dates* period on line and bar charts, which then span the chosen range. Without it they ignore the bar. A widget may use Filament's `InteractsWithPageFilters` directly and read `$this->pageFilters`: `preset` is a number of days, a period key or `custom`, and `startDate` and `endDate` are set only for `custom`. Change the presets, the default and the time zone:

```php
FilamentDashboardsPlugin::make()
    ->datePresets([1, 7, 30, 365])
    ->defaultDatePreset(7)
    ->timezoneUsing(fn (): string => auth()->user()->timezone);
```

Named periods (today, this week, this month, last month, this quarter, this year, last 30 days) are offered on stats cards and line and bar charts, and can be date bar presets next to day counts. Add your own, such as a fiscal year, by extending `DatePeriod` (key, label, `range($now)` and, for calendar periods, `previous($range)` used by comparisons). Weeks start on Monday unless you choose another day:

```php
FilamentDashboardsPlugin::make()
    ->datePeriods([FiscalYear::class])
    ->datePresets([1, 30, 'month', 'fiscal'])
    ->weekStartsOn(CarbonInterface::SUNDAY);
```

## Coded table widgets

For a table in your own widget, [waguilar33/filament-data-grid](https://github.com/Waguilar33/filament-data-grid) adds search, pagination, totals and child rows in the browser. The plugin does not need it; the builder's tables keep using Filament tables. Render a `DataGridEntry` from a plain `Widget`:

```php
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Waguilar\FilamentDataGrid\DataGridEntry;

class SalesByCategory extends Widget implements DashboardWidget, HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithDashboard;
    use InteractsWithDashboardDates;
    use InteractsWithSchemas {
        InteractsWithSchemas::__get insteadof InteractsWithDashboardDates;
    }

    protected string $view = 'widgets.data-grid-widget';

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            DataGridEntry::make('categories')
                ->state(fn (): array => $this->rows())
                ->table([...])
                ->schema([...]),
        ]);
    }
}
```

```blade
<x-filament-widgets::widget>
    {{ $this->content }}
</x-filament-widgets::widget>
```

Both `InteractsWithSchemas` and `InteractsWithDashboardDates` (through Filament's `InteractsWithPageFilters`) define `__get`, so PHP refuses the class until you pick one; take the schemas one, as above, so `$this->content` renders. `dateRange()` still works, because it reads the page filters directly.

## Who can do what

| | Who |
|---|---|
| View | The owner, users and roles it is shared with, everyone for system dashboards |
| Edit (widgets, layout, settings) | The owner and Edit shares |
| Share and delete | The owner only |
| Clone | Anyone who can view it and may create dashboards |
| Set as home | Anyone who can view it |

Anyone who can open a dashboard can see the saved widgets its owner placed on it, and edit them with an Edit share on the dashboard; the access ends when the dashboard is unshared or the widget removed from it. A widget placed by someone other than its owner gives no extra access. Deleting a saved widget removes it from every dashboard.

Without any other package, every signed-in user can create dashboards and add any widget whose `canView()` passes. Narrow it with:

```php
FilamentDashboardsPlugin::make()
    ->authorizeWidgetsUsing(fn ($user, string $widget): bool => ...)
    ->canCreateDashboardsUsing(fn ($user): bool => ...)
    ->shareableUsersUsing(fn (?Model $tenant): Builder => ...);
```

`shareableUsersUsing` limits the users offered in Share, for example to the members of the current tenant.

A policy you register for the dashboard model decides who sees the Dashboards list (`viewAny`) and who creates dashboards (`create`); anyone who owns a dashboard or has one shared with them sees the list and opens it regardless. Register a policy for `BuilderWidget` with `viewAny` and `create` to gate the Widgets list and building widgets from roles the same way; users still need at least one data source. Viewing, editing, sharing and deleting a given dashboard or widget always follow ownership and shares. With no policy, every user with a data source can use the Widgets list and build widgets.

## Changing the plugin's screens

Create dashboard and Settings open in a medium slide-over with the form in one column. Each hook receives the plugin's configured form or action and returns it; your changes win:

```php
FilamentDashboardsPlugin::make()
    ->modifyDashboardFormUsing(fn (Schema $schema): Schema => $schema->columns(2))
    ->modifyCreateDashboardActionUsing(fn (CreateAction $action): CreateAction => $action->slideOver(false))
    ->modifySettingsActionUsing(fn (EditAction $action): EditAction => $action)
    ->modifyShareActionUsing(fn (ShareAction $action): ShareAction => $action)
    ->modifyAddWidgetActionUsing(fn (Action $action): Action => $action);
```

Every other action the plugin builds passes through one hook with its name, after the specific hooks above: `dashboard.*` on the dashboard page (`edit`, `share`, `settings`, `emailMe`, `alerts`, `print`, `duplicate`, `setAsHome`, `removeHome`, `delete`, `doneEditing`, `addWidget`, `buildWidget`, `editWidget`, `widgetHistory`, `duplicateItem`, `removeItem`), `dashboards.*` on the Dashboards list (`create` and the row actions), `widgets.*` on the Widgets list (`create`, `buildWidget`, `edit`, `duplicate`, `share`, `history`, `delete`), `home.openHome`, and `widget.export` / `widget.drillDown` inside widgets:

```php
FilamentDashboardsPlugin::make()
    ->modifyActionUsing(fn (Action $action, string $name): Action => match ($name) {
        'dashboard.print' => $action->label('Imprimir'),
        default => $action,
    });
```

Numbers and dates the plugin writes (cards, percentages, chart labels, printed ranges) go through two hooks, for local separators and date styles. A data source field's own `formatUsing` still wins for its values:

```php
FilamentDashboardsPlugin::make()
    ->formatNumbersUsing(fn (int|float $value, int $decimals): string => number_format($value, $decimals, '.', ','))
    ->formatDatesUsing(fn (CarbonInterface $date, string $context): string => $date->translatedFormat($context === 'range' ? 'd/m/Y' : 'M j'));
```

`$context` is `day`, `week`, `month` or `year` for chart labels and `range` for date ranges.

Every icon the plugin draws has a Filament icon alias, so an app using another icon set swaps them the Filament way; the aliases are the constants on `FilamentDashboardsIcon`:

```php
FilamentIcon::register([
    FilamentDashboardsIcon::NAVIGATION_DASHBOARDS => 'fas-table-columns',
    FilamentDashboardsIcon::PRINT => 'fas-print',
]);
```

The dashboard page's header shows Edit and Share, and a menu with Settings, Duplicate, Set as home (or Remove as home) and Delete. Rearrange, regroup or drop them; the hook receives the built actions keyed by `edit`, `share`, `settings`, `duplicate`, `setAsHome`, `removeHome` and `delete`, and returns the header:

```php
FilamentDashboardsPlugin::make()
    ->headerActionsUsing(fn (array $actions): array => [
        $actions['edit'],
        ActionGroup::make([$actions['share'], $actions['settings'], $actions['delete']]),
    ]);
```

Add widget lives in the Edit mode banner; Edit hides itself while editing.

The Dashboards and Widgets lists are yours to shape. The plugin builds each table (columns, sort, row actions), then hands it to your hook with its ready-made row actions, keyed by name:

| Hook | Action keys | Default row actions |
|---|---|---|
| `modifyDashboardsTableUsing` | `setAsHome`, `removeHome`, `share`, `settings`, `duplicate`, `delete` | Set as home (or Remove as home), then a menu with Share, Settings, Duplicate and Delete |
| `modifyWidgetsTableUsing` | `edit`, `duplicate`, `share`, `delete` | Edit, then a menu with Share, Duplicate and Delete |

```php
FilamentDashboardsPlugin::make()
    ->modifyDashboardsTableUsing(fn (Table $table, array $actions): Table => $table->recordActions([
        $actions['setAsHome'],
        $actions['removeHome'],
        $actions['share']->color('gray'),
        ActionGroup::make([$actions['settings'], $actions['duplicate'], $actions['delete']]),
    ]))
    ->modifyWidgetsTableUsing(fn (Table $table, array $actions): Table => $table->recordActions([
        $actions['edit'],
        $actions['duplicate']->color('gray'),
        ActionGroup::make([$actions['share'], $actions['delete']]),
    ]));
```

Each action keeps its own visibility and permission checks, so you only place them. A dashboard row still opens the dashboard; editing its widgets stays on that page.

The dashboard form is used by both create and settings. The share hook applies to dashboards and saved widgets.

Users pick a dashboard's navigation group from the panel's registered groups. When your group labels are translated, give each a stable key so a dashboard saved in one language shows in another:

```php
FilamentDashboardsPlugin::make()
    ->navigationGroupsUsing(fn (): array => collect(NavigationGroupEnum::cases())
        ->mapWithKeys(fn (NavigationGroupEnum $group): array => [$group->value => $group->getLabel()])
        ->all());
```

The key is stored and shown as its label in the navigation. Replace the Heroicon icon select with your own field; it receives the field name, and any blade-icons name it stores renders:

```php
FilamentDashboardsPlugin::make()
    ->navigationIconFieldUsing(fn (string $name): Field => IconPicker::make($name));
```

## Works better with Filament Guardian

Install [waguilar33/filament-guardian](https://github.com/Waguilar33/filament-guardian) and access is managed from the role screen instead of code:

| | Without Guardian | With Guardian |
|---|---|---|
| Who can add each widget | Code in `canView()` or `authorizeWidgetsUsing()` | A `View:<Widget>` permission per widget, ticked per role or per user |
| Who can create dashboards | `canCreateDashboardsUsing()` | `Create:Dashboard` on the role form |
| Sharing | With users | With users and whole roles |

Guardian finds dashboard widgets the same way it finds every panel widget. Check the permission in the widget's `canView()`.

Role sharing switches on whenever the user model has a spatie/laravel-permission `roles()` relationship.

## Emails and alerts

Turn on dashboard emails and stats card alerts, and schedule the command that sends them:

```php
FilamentDashboardsPlugin::make()
    ->scheduledNotifications()
    ->recipientContextUsing(function (Model $user, ?Model $tenant, Closure $next): mixed {
        // Set anything your data sources need for this person and company, such as a team id, then run $next().
        return $next();
    });
```

```php
Schedule::command('dashboards:notify')->hourly();
```

Anyone who can open a dashboard can then choose *Email me* (every morning or every Monday, from 7:00 in the dashboard time zone) and set *Alerts* on its stats cards. Each email and alert is computed as its recipient, in their panel, company and language. Publish and run the new migrations after updating the plugin.

## Tenancy

On a panel with tenancy, dashboards, shares and home pages belong to the current tenant; system dashboards are shared by every tenant.

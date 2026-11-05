# Filament Dashboards

Users build their own dashboards from your panel's widgets, share them with other users or roles, clone them, and pick one as their home page. You can ship read-only system dashboards everyone can clone.

Private beta. The rules behind every behaviour are in [docs/architecture](docs/architecture/README.md).

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

A number stored scaled, such as cents, is shown in its real units with `Field::number('total', 'Total')->divideBy(100)`; filters on it are compared in those units too. Format how its values read on cards and in tables with `->formatUsing(fn (int|float $value): string => Number::currency($value, 'GTQ'))`.

Hide a source from users who may not use it; they are not offered it in the builder, and widgets built on it are left off their dashboards:

```php
public function canView(Model $user, ?Model $tenant): bool
{
    return $user->can('viewAny', Order::class);
}
```

Users then pick New widget (stats cards, table, line, bar, scatter, pie, doughnut, polar area), choose a source, a metric and filters, and see a preview before saving. Saved widgets appear under Widgets, where they can be shared, cloned and deleted like dashboards. Ship ready-made ones as system widgets:

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

## Who can do what

| | Who |
|---|---|
| View | The owner, users and roles it is shared with, everyone for system dashboards |
| Edit (widgets, layout, settings) | The owner and Edit shares |
| Share and delete | The owner only |
| Clone | Anyone who can view it and may create dashboards |
| Set as home | Anyone who can view it |

Without any other package, every signed-in user can create dashboards and add any widget whose `canView()` passes. Narrow it with:

```php
FilamentDashboardsPlugin::make()
    ->authorizeWidgetsUsing(fn ($user, string $widget): bool => ...)
    ->canCreateDashboardsUsing(fn ($user): bool => ...)
    ->shareableUsersUsing(fn (?Model $tenant): Builder => ...);
```

`shareableUsersUsing` limits the users offered in Share, for example to the members of the current tenant.

## Changing the plugin's screens

Each hook receives the plugin's configured form or action and returns it; your changes win:

```php
FilamentDashboardsPlugin::make()
    ->modifyDashboardFormUsing(fn (Schema $schema): Schema => $schema->columns(1))
    ->modifyCreateDashboardActionUsing(fn (CreateAction $action): CreateAction => $action->slideOver(false)->modalWidth(Width::Medium))
    ->modifySettingsActionUsing(fn (EditAction $action): EditAction => $action)
    ->modifyShareActionUsing(fn (ShareAction $action): ShareAction => $action)
    ->modifyAddWidgetActionUsing(fn (Action $action): Action => $action);
```

The dashboard form is used by both create and settings. The share hook applies to dashboards and saved widgets.

## Works better with Filament Guardian

Install [waguilar33/filament-guardian](https://github.com/Waguilar33/filament-guardian) and access is managed from the role screen instead of code:

| | Without Guardian | With Guardian |
|---|---|---|
| Who can add each widget | Code in `canView()` or `authorizeWidgetsUsing()` | A `View:<Widget>` permission per widget, ticked per role or per user |
| Who can create dashboards | `canCreateDashboardsUsing()` | `Create:Dashboard` on the role form |
| Sharing | With users | With users and whole roles |

Guardian finds dashboard widgets the same way it finds every panel widget. Check the permission in the widget's `canView()`.

Role sharing switches on whenever the user model has a spatie/laravel-permission `roles()` relationship.

## Tenancy

On a panel with tenancy, dashboards, shares and home pages belong to the current tenant; system dashboards are shared by every tenant.

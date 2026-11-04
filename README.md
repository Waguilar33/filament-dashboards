# Filament Dashboards

Users build their own dashboards from your panel's widgets, share them with other users or roles, clone them, and pick one as their home page. You can ship read-only system dashboards everyone can clone.

Private beta. The rules behind every behaviour are in [docs/architecture](docs/architecture/README.md).

## Install

```bash
composer require waguilar33/filament-dashboards:@beta
php artisan filament-dashboards:install
```

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

Run `php artisan dashboards:sync` on every deploy. Everyone can view system dashboards and clone them; nobody can edit or delete them.

## Who can do what

| | Who |
|---|---|
| View | The owner, users and roles it is shared with, everyone for system dashboards |
| Edit (widgets, layout, settings) | The owner and Edit shares |
| Share and delete | The owner only |
| Clone, set as home | Anyone who can view it and may create dashboards |

Without any other package, every signed-in user can create dashboards and add any widget whose `canView()` passes. Narrow it with:

```php
FilamentDashboardsPlugin::make()
    ->authorizeWidgetsUsing(fn ($user, string $widget): bool => ...)
    ->canCreateDashboardsUsing(fn ($user): bool => ...)
    ->shareableUsersUsing(fn (?Model $tenant): Builder => ...);
```

`shareableUsersUsing` limits the users offered in Share, for example to the members of the current tenant.

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

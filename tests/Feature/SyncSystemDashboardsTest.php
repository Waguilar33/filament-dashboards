<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Waguilar\FilamentDashboards\Actions\SyncSystemDashboards;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Tests\Fixtures\SalesDashboard;
use Workbench\App\Filament\Widgets\OrdersOverview;
use Workbench\App\Filament\Widgets\SignupsChart;

beforeEach(function (): void {
    SalesDashboard::$items = [['widget' => OrdersOverview::class, 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]];
});

it('copies a registered system dashboard into the database', function (): void {
    app(SyncSystemDashboards::class)->handle('demo', [SalesDashboard::class]);

    $dashboard = Dashboard::query()->sole();

    expect($dashboard->system_key)->toBe('sales')
        ->and($dashboard->name)->toBe('Sales')
        ->and($dashboard->owner_id)->toBeNull()
        ->and($dashboard->tenant_id)->toBeNull()
        ->and($dashboard->items()->get(['widget', 'x', 'y', 'w', 'h'])->toArray())
        ->toBe([['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]]);
});

it('replaces the widgets of a system dashboard on every sync', function (): void {
    app(SyncSystemDashboards::class)->handle('demo', [SalesDashboard::class]);
    SalesDashboard::$items = [['widget' => SignupsChart::class, 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]];

    app(SyncSystemDashboards::class)->handle('demo', [SalesDashboard::class]);

    expect(Dashboard::query()->count())->toBe(1)
        ->and(Dashboard::query()->sole()->items()->pluck('widget')->all())->toBe(['signups-chart']);
});

it('removes system dashboards no longer registered, leaving user dashboards and other panels', function (): void {
    app(SyncSystemDashboards::class)->handle('demo', [SalesDashboard::class]);
    app(SyncSystemDashboards::class)->handle('admin', [SalesDashboard::class]);
    $mine = dashboard(user(), ['slug' => 'mine']);

    app(SyncSystemDashboards::class)->handle('demo', []);

    expect(Dashboard::query()->orderBy('id')->get()->map(fn (Dashboard $dashboard): array => [$dashboard->panel, $dashboard->slug])->all())
        ->toBe([['admin', 'sales'], ['demo', 'mine']]);
});

it('syncs every panel that uses the plugin from the command', function (): void {
    Filament::registerPanel(Panel::make()->id('plain'));
    Filament::getPanel('demo')->getPlugin('filament-dashboards')->systemDashboards([SalesDashboard::class]);

    $this->artisan('dashboards:sync')->assertSuccessful();

    expect(Dashboard::query()->pluck('panel')->all())->toBe(['demo']);
});

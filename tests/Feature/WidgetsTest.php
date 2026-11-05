<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Widgets\AccountWidget;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Tests\Fixtures\HiddenWidget;
use Workbench\App\Filament\Widgets\OrdersOverview;
use Workbench\App\Filament\Widgets\SignupsChart;

it('lists the panel widgets that implement the contract, keyed by their dashboard key', function (): void {
    $panel = Panel::make()->id('test')->widgets([OrdersOverview::class, AccountWidget::class, HiddenWidget::class]);

    expect(FilamentDashboardsPlugin::make()->getDashboardWidgets($panel))->toBe([
        'orders-overview' => OrdersOverview::class,
        'hidden-widget' => HiddenWidget::class,
    ]);
});

it('finds widgets the panel discovers', function (): void {
    expect(FilamentDashboardsPlugin::make()->getDashboardWidgets(Filament::getPanel('demo')))
        ->toContain(OrdersOverview::class, SignupsChart::class);
});

it('offers only widgets whose canView passes', function (): void {
    $panel = Panel::make()->id('test')->widgets([OrdersOverview::class, HiddenWidget::class]);

    expect(FilamentDashboardsPlugin::make()->getAvailableWidgets($panel, user()))->toBe(['orders-overview' => OrdersOverview::class]);
});

it('offers only widgets the authorize callback allows', function (): void {
    $panel = Panel::make()->id('test')->widgets([OrdersOverview::class, SignupsChart::class]);
    $plugin = FilamentDashboardsPlugin::make()->authorizeWidgetsUsing(fn ($user, string $widget): bool => $widget === SignupsChart::class);

    expect($plugin->getAvailableWidgets($panel, user()))->toBe(['signups-chart' => SignupsChart::class]);
});

it('describes a widget from its class name by default', function (): void {
    expect(SignupsChart::dashboardLabel())->toBe('Signups Chart')
        ->and(OrdersOverview::dashboardSize())->toBe(['w' => 6, 'h' => 4])
        ->and(OrdersOverview::dashboardPreview())->toBeNull()
        ->and(HiddenWidget::dashboardDescription())->toBeNull()
        ->and(HiddenWidget::dashboardLabel())->toBe('Hidden');
});

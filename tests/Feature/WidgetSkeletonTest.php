<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Schemas\Components\View;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\ResolveWidgetSkeleton;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Waguilar\FilamentDashboards\Tests\Fixtures\Charts\DeliveriesLineChart;
use Waguilar\FilamentDashboards\Tests\Fixtures\Charts\PlainBarChart;
use Waguilar\FilamentDashboards\Tests\Fixtures\Charts\ShareDoughnutChart;
use Waguilar\FilamentDashboards\Tests\Fixtures\Charts\UnknownTypeChart;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

it('shapes a code chart skeleton from its chart type without mounting it or reading its data', function (): void {
    expect(app(ResolveWidgetSkeleton::class)->handle(DeliveriesLineChart::class))
        ->toBe(['shape' => 'line', 'heading' => true, 'description' => true, 'action' => true]);
});

it('mirrors only the header parts a code chart has', function (): void {
    expect(app(ResolveWidgetSkeleton::class)->handle(PlainBarChart::class))
        ->toBe(['shape' => 'bar', 'heading' => false, 'description' => false, 'action' => false])
        ->and(app(ResolveWidgetSkeleton::class)->handle(ShareDoughnutChart::class))
        ->toBe(['shape' => 'pie', 'heading' => true, 'description' => false, 'action' => false]);
});

it('falls back to a neutral chart when the chart type cannot be read', function (): void {
    expect(app(ResolveWidgetSkeleton::class)->handle(UnknownTypeChart::class)['shape'])->toBe('chart');
});

it('shapes builder charts from their type and settings', function (): void {
    $owner = user();
    $skeleton = fn (BuilderWidgetType $type, array $config = []) => app(ResolveWidgetSkeleton::class)
        ->handle(BuilderWidgetType::class, builderWidget($owner, ['type' => $type, 'config' => ['source' => 'orders', ...$config]]));

    expect($skeleton(BuilderWidgetType::Pie))->toBe(['shape' => 'pie', 'heading' => true, 'description' => false, 'action' => false])
        ->and($skeleton(BuilderWidgetType::Line, ['date_field' => 'created_at', 'period' => '30d']))->toBe(['shape' => 'line', 'heading' => true, 'description' => true, 'action' => false])
        ->and($skeleton(BuilderWidgetType::Bar, ['date_field' => 'created_at', 'period' => 'dashboard', 'viewer_dates' => true]))->toBe(['shape' => 'bar', 'heading' => true, 'description' => false, 'action' => true])
        ->and($skeleton(BuilderWidgetType::Scatter)['shape'])->toBe('chart');
});

it('gives every grid item the skeleton of its widget and draws chart headers from Filament sections', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $keys = [
        'signups-chart' => 'line',
        'orders-overview' => 'stats',
        builderWidget($owner, ['type' => BuilderWidgetType::Table, 'config' => ['source' => 'orders', 'columns' => ['customer']]])->dashboardKey() => 'table',
        builderWidget($owner, ['type' => BuilderWidgetType::Pie, 'config' => ['source' => 'orders', 'group_field' => 'channel', 'metric' => 'count']])->dashboardKey() => 'pie',
    ];

    foreach (array_keys($keys) as $index => $key) {
        $dashboard->items()->create(['widget' => $key, 'x' => 0, 'y' => $index * 4, 'w' => 6, 'h' => 4]);
    }

    $this->actingAs($owner);
    $page = Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug]);
    $items = $page->instance()
        ->getSchema('content')?->getComponent(fn (mixed $component): bool => $component instanceof View, withHidden: true)?->getViewData()['items'] ?? [];

    expect(array_column($items, 'skeleton'))->toBe(array_values($keys));
    $page->assertSeeHtml('data-shape="line"')
        ->assertSeeHtml('data-shape="pie"')
        ->assertSeeHtml('fi-dashboards-skeleton-heading')
        ->assertDontSeeHtml('data-shape="stats"');
});

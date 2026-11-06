<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Schemas\Components\View;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\CloneDashboard;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

it('lets charts and tables take a chosen height while other widgets fit their content', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $chart = $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6, 'fits_content' => false]);
    $stats = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 2, 'fits_content' => false]);
    $this->actingAs($owner);

    $items = collect(Livewire::withQueryParams(['editing' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertSeeHtml('gs-resize-handles="e,w,s"')
        ->assertSeeHtml("mountAction('fitItemHeight', { item: {$chart->id} })")
        ->assertDontSeeHtml("mountAction('fitItemHeight', { item: {$stats->id} })")
        ->instance()
        ->getSchema('content')?->getComponent(fn (mixed $component): bool => $component instanceof View, withHidden: true)?->getViewData()['items'] ?? [])
        ->keyBy('id');

    expect($items[$chart->id])->toMatchArray(['adjustableHeight' => true, 'fixedHeight' => true])
        ->and($items[$stats->id])->toMatchArray(['adjustableHeight' => false, 'fixedHeight' => false]);
});

it('saves a chosen height with the layout, never below the minimum', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $chosen = $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 8]);
    $fitting = $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 8, 'fits_content' => false]);

    $this->actingAs($owner);
    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->call('saveDashboardLayout', [
        ['id' => $chosen->id, 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2, 'fitsContent' => false],
        ['id' => $fitting->id, 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 2, 'fitsContent' => 'no'],
    ]);

    expect($chosen->refresh()->only(['h', 'fits_content']))->toBe(['h' => 4, 'fits_content' => false])
        ->and($fitting->refresh()->only(['h', 'fits_content']))->toBe(['h' => 4, 'fits_content' => false]);
});

it('fits an item to its content again and redraws the grid', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6, 'fits_content' => false]);
    $this->actingAs($owner);
    $signature = fn (): string => Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->instance()
        ->getSchema('content')?->getComponent(fn (mixed $component): bool => $component instanceof View, withHidden: true)?->getViewData()['signature'] ?? '';

    $before = $signature();
    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->callAction('fitItemHeight', arguments: ['item' => $item->id]);

    expect($item->refresh()->fits_content)->toBeTrue()
        ->and($signature())->not->toBe($before);
});

it('places a new builder chart at its declared height and other builder widgets fitting their content', function (BuilderWidgetType $type, bool $fitsContent): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $widget = builderWidget($owner, ['type' => $type, 'config' => ['source' => 'orders', 'metric' => 'count']]);

    $this->actingAs($owner);
    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->callAction('addWidget', data: ['widget' => $widget->dashboardKey()])->assertHasNoActionErrors();

    expect($dashboard->items()->sole()->only(['h', 'fits_content']))->toBe(['h' => $type->defaultSize()['h'], 'fits_content' => $fitsContent]);
})->with([
    'bar chart' => [BuilderWidgetType::Bar, false],
    'table' => [BuilderWidgetType::Table, true],
]);

it('keeps a chosen height when the item is duplicated or the dashboard cloned', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6, 'fits_content' => false]);

    $this->actingAs($owner);
    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->callAction('duplicateItem', arguments: ['item' => $item->id]);
    $clone = app(CloneDashboard::class)->handle($dashboard, user(), null, 'Copy', 'copy');

    expect($dashboard->items()->whereKeyNot($item->id)->sole()->fits_content)->toBeFalse()
        ->and($clone->items()->pluck('fits_content')->all())->toBe([false, false]);
});

it('gives each grid item a skeleton shaped like its widget', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $keys = [
        'signups-chart' => 'chart',
        'orders-overview' => 'stats',
        builderWidget($owner, ['type' => BuilderWidgetType::Table, 'config' => ['source' => 'orders', 'columns' => ['customer']]])->dashboardKey() => 'table',
        builderWidget($owner, ['type' => BuilderWidgetType::Doughnut, 'config' => ['source' => 'orders', 'group_field' => 'channel', 'metric' => 'count']])->dashboardKey() => 'pie',
    ];

    foreach (array_keys($keys) as $index => $key) {
        $dashboard->items()->create(['widget' => $key, 'x' => 0, 'y' => $index * 4, 'w' => 6, 'h' => 4]);
    }

    $this->actingAs($owner);
    $items = Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->instance()
        ->getSchema('content')?->getComponent(fn (mixed $component): bool => $component instanceof View, withHidden: true)?->getViewData()['items'] ?? [];

    expect(array_combine(array_column($items, 'id'), array_column($items, 'skeleton')))
        ->toBe(array_combine($dashboard->items()->orderBy('y')->pluck('id')->all(), array_values($keys)));
});

<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Waguilar\FilamentDashboards\Widgets\BuilderChartWidget;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
    DemoOrderFactory::new()->count(3)->create(['channel' => 'web', 'status' => 'paid', 'ordered_at' => now()]);
    DemoOrderFactory::new()->count(2)->create(['channel' => 'phone', 'status' => 'pending', 'ordered_at' => now()]);
    DemoOrderFactory::new()->create(['channel' => 'store', 'status' => 'shipped', 'ordered_at' => now()]);
    $this->actingAs(user());
});

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function paletteChart(string $type, array $config): array
{
    $chart = Livewire::test(BuilderChartWidget::class, ['previewType' => $type, 'previewConfig' => ['source' => 'orders', 'metric' => 'count', ...$config]])->instance();

    return (fn (): array => $this->getData())->call($chart);
}

it('leaves the colours to the panel theme and lists slices largest first unless told to keep the field order', function (array $config, array $labels, string $palette): void {
    $data = paletteChart('doughnut', ['group_field' => 'channel', ...$config]);

    expect($data['labels'])->toBe($labels)
        ->and($data['datasets'][0])->not->toHaveKey('backgroundColor')
        ->and($data['palette'])->toBe(['kind' => $palette, 'other' => false]);
})->with([
    'defaults' => [[], ['Web', 'Phone', 'Store'], 'varied'],
    'field order' => [['series_order' => 'field'], ['Store', 'Web', 'Phone'], 'varied'],
    'single color' => [['palette' => 'single'], ['Web', 'Phone', 'Store'], 'single'],
]);

it('lists the series of a split chart in the order of the field when asked, with Other last', function (): void {
    $config = ['date_field' => 'ordered_at', 'period' => '7d', 'split_field' => 'status'];
    $series = fn (array $data): array => array_column($data['datasets'], 'label');

    expect($series(paletteChart('bar', $config)))->toBe(['Paid', 'Pending', 'Shipped'])
        ->and($series(paletteChart('bar', [...$config, 'series_order' => 'field'])))->toBe(['Pending', 'Paid', 'Shipped'])
        ->and(paletteChart('bar', $config)['palette'])->toBe(['kind' => 'varied', 'other' => false]);

    FilamentDashboardsPlugin::get()->builderLimits(['series' => 2]);

    $folded = paletteChart('bar', [...$config, 'series_order' => 'field']);

    expect($series($folded))->toBe(['Paid', 'Other'])
        ->and($folded['palette'])->toBe(['kind' => 'varied', 'other' => true]);
});

it('hands the page the panel colours in registration order and any fixed list', function (): void {
    $panel = Filament::getPanel('demo');

    expect(FilamentDashboardsPlugin::get()->chartColorSettings($panel))->toBe(['names' => ['danger', 'gray', 'info', 'primary', 'success', 'warning'], 'palette' => null, 'fixed' => null]);

    $panel->colors(['earth' => '#a0522d', 'primary' => '#0f766e', 'pink' => '#db2777']);
    FilamentDashboardsPlugin::get()->chartColors(['#111111', '#222222'])->chartPalette(['info', 'missing', 'earth', 'info']);

    expect(FilamentDashboardsPlugin::get()->chartColorSettings($panel))->toBe(['names' => ['earth', 'primary', 'pink', 'danger', 'gray', 'info', 'success', 'warning'], 'palette' => ['info', 'earth'], 'fixed' => ['#111111', '#222222']]);
});

it('saves the colors and order chosen in the builder', function (string $type, array $data): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('buildWidget', data: ['name' => 'Sizes', 'source' => 'orders', 'metric' => 'count', 'palette' => 'single', 'series_order' => 'field', ...$data], arguments: ['type' => $type])
        ->assertHasNoActionErrors();

    expect(BuilderWidget::query()->sole()->config)->toMatchArray(['palette' => 'single', 'series_order' => 'field']);
})->with([
    'split bar' => ['bar', ['date_field' => 'ordered_at', 'period' => '30d', 'split_field' => 'channel']],
    'doughnut' => ['doughnut', ['group_field' => 'channel']],
]);

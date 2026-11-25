<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
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

it('leaves the colours to the panel theme, varied and largest first, unless told the values run in order', function (string $palette, array $labels): void {
    $data = paletteChart('doughnut', ['group_field' => 'channel', ...($palette === '' ? [] : ['palette' => $palette])]);

    expect($data['labels'])->toBe($labels)
        ->and($data['datasets'][0])->not->toHaveKey('backgroundColor')
        ->and($data['palette'])->toBe(['kind' => $palette === '' ? 'varied' : $palette, 'other' => false]);
})->with([
    'no choice' => ['', ['Web', 'Phone', 'Store']],
    'varied' => ['varied', ['Web', 'Phone', 'Store']],
    'ordered' => ['ordered', ['Store', 'Web', 'Phone']],
]);

it('lists the series of a split chart in the order of the field when they run in order, with Other last', function (): void {
    $config = ['date_field' => 'ordered_at', 'period' => '7d', 'split_field' => 'status'];
    $series = fn (array $data): array => array_column($data['datasets'], 'label');

    expect($series(paletteChart('bar', $config)))->toBe(['Paid', 'Pending', 'Shipped'])
        ->and($series(paletteChart('bar', [...$config, 'palette' => 'ordered'])))->toBe(['Pending', 'Paid', 'Shipped'])
        ->and(paletteChart('bar', [...$config, 'palette' => 'ordered'])['palette'])->toBe(['kind' => 'ordered', 'other' => false]);

    FilamentDashboardsPlugin::get()->builderLimits(['series' => 2]);

    $folded = paletteChart('bar', [...$config, 'palette' => 'ordered']);

    expect($series($folded))->toBe(['Paid', 'Other'])
        ->and($folded['palette'])->toBe(['kind' => 'ordered', 'other' => true]);
});

it('uses the colours the panel sets instead of the theme', function (): void {
    FilamentDashboardsPlugin::get()->chartColors(['#111111', '#222222']);

    $breakdown = paletteChart('pie', ['group_field' => 'channel']);
    $split = paletteChart('line', ['date_field' => 'ordered_at', 'period' => '7d', 'split_field' => 'channel']);

    expect($breakdown)->not->toHaveKey('palette')
        ->and($breakdown['datasets'][0]['backgroundColor'])->toBe(['#111111', '#222222', '#111111'])
        ->and($split)->not->toHaveKey('palette')
        ->and(array_column($split['datasets'], 'borderColor'))->toBe(['#111111', '#222222', '#111111']);
});

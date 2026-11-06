<?php

declare(strict_types=1);

use Livewire\Livewire;
use Waguilar\FilamentDashboards\Widgets\BuilderChartWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderStatsWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderTableWidget;

beforeEach(function (): void {
    $this->actingAs(user());
});

$stale = [['rules' => [['field' => 'renamed', 'operator' => 'is', 'value' => 'x']]]];

it('marks a stats card whose saved filter no longer matches its source', function () use ($stale): void {
    Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewConfig' => ['cards' => [
        ['source' => 'orders', 'metric' => 'count', 'label' => 'Paid orders', 'filters' => $stale],
    ]]])
        ->assertSee('Paid orders')
        ->assertSee(__('filament-dashboards::builder.needs_attention'));
});

it('marks a chart whose saved filter no longer matches its source', function () use ($stale): void {
    $chart = Livewire::test(BuilderChartWidget::class, ['previewType' => 'pie', 'previewConfig' => ['source' => 'orders', 'metric' => 'count', 'group_field' => 'channel', 'filters' => $stale]])->instance();

    expect($chart->getDescription())->toBe(__('filament-dashboards::builder.needs_attention'));
});

it('marks a table whose saved filter no longer matches its source', function () use ($stale): void {
    Livewire::test(BuilderTableWidget::class, ['previewType' => 'table', 'previewConfig' => ['source' => 'orders', 'columns' => ['customer'], 'filters' => $stale]])
        ->assertSee(__('filament-dashboards::builder.needs_attention'));
});

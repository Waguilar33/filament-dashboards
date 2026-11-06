<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\Builder\Actions\CalculateSummary;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\DemoOrder;

beforeEach(function (): void {
    DemoOrder::factory()->create(['channel' => 'web', 'status' => 'paid', 'total' => 10, 'items_count' => 1]);
    DemoOrder::factory()->create(['channel' => 'web', 'status' => 'paid', 'total' => 20, 'items_count' => 3]);
    DemoOrder::factory()->create(['channel' => 'store', 'status' => 'shipped', 'total' => 100, 'items_count' => 2]);
    DemoOrder::factory()->create(['channel' => 'phone', 'status' => 'cancelled', 'total' => 5, 'items_count' => 4]);
});

it('calculates several metrics per group, sorted by the first descending, with totals over every row', function (): void {
    $summary = app(CalculateSummary::class)->handle(new OrdersSource, user(), null, 'channel', [
        ['metric' => Metric::Sum, 'field' => 'total'],
        ['metric' => Metric::Count, 'field' => null],
        ['metric' => Metric::Average, 'field' => 'items_count'],
    ], [], 2);

    expect($summary)->toEqual([
        'rows' => [
            ['group' => 'Store', 'key' => 'store', 'values' => [100, 1, 2]],
            ['group' => 'Web', 'key' => 'web', 'values' => [30, 2, 2]],
        ],
        'totals' => [135, 4, 2.5],
    ]);
});

it('applies the filters to the rows and the totals', function (): void {
    $summary = app(CalculateSummary::class)->handle(new OrdersSource, user(), null, 'status', [
        ['metric' => Metric::Count, 'field' => null],
    ], [[['field' => 'channel', 'operator' => 'is', 'value' => 'web']]]);

    expect($summary)->toEqual(['rows' => [['group' => 'Paid', 'key' => 'paid', 'values' => [2]]], 'totals' => [2]]);
});

<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\Builder\Actions\CalculateMetric;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Tests\Fixtures\WebOrdersSource;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\DemoOrder;

beforeEach(function (): void {
    DemoOrder::factory()->create(['channel' => 'web', 'status' => 'paid', 'total' => 10, 'items_count' => 1]);
    DemoOrder::factory()->create(['channel' => 'web', 'status' => 'pending', 'total' => 20, 'items_count' => 2]);
    DemoOrder::factory()->create(['channel' => 'store', 'status' => 'paid', 'total' => 60, 'items_count' => 3]);
});

/**
 * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
 */
function metric(Metric $metric, ?string $field = null, array $filters = [], ?DataSource $source = null): int|float
{
    return app(CalculateMetric::class)->handle($source ?? new OrdersSource, user(), null, $metric, $field, $filters);
}

it('calculates every metric', function (Metric $metric, ?string $field, int|float $expected): void {
    expect(metric($metric, $field))->toEqual($expected);
})->with([
    'count' => [Metric::Count, null, 3],
    'count ignores the field' => [Metric::Count, 'total', 3],
    'sum' => [Metric::Sum, 'total', 90],
    'sum of integers' => [Metric::Sum, 'items_count', 6],
    'average' => [Metric::Average, 'total', 30],
    'min' => [Metric::Min, 'total', 10],
    'max' => [Metric::Max, 'total', 60],
]);

it('applies the filters', function (): void {
    $filters = [[['field' => 'status', 'operator' => 'is', 'value' => 'paid']]];

    expect(metric(Metric::Count, null, $filters))->toBe(2)
        ->and(metric(Metric::Sum, 'total', $filters))->toEqual(70)
        ->and(metric(Metric::Average, 'total', $filters))->toEqual(35);
});

it('stays inside the scoping of the source', function (): void {
    $filters = [[['field' => 'channel', 'operator' => 'is', 'value' => 'store']], [['field' => 'total', 'operator' => 'greater_than', 'value' => 0]]];

    expect(metric(Metric::Count, null, $filters, new WebOrdersSource))->toBe(2)
        ->and(metric(Metric::Sum, 'total', [], new WebOrdersSource))->toEqual(30);
});

it('returns zero for an empty set', function (Metric $metric): void {
    $filters = [[['field' => 'status', 'operator' => 'is', 'value' => 'cancelled']]];

    expect(metric($metric, 'total', $filters))->toBe(0);
})->with(Metric::cases());

it('rejects a metric that needs a number field', function (?string $field): void {
    metric(Metric::Sum, $field);
})->with([
    'no field' => [null],
    'empty field' => [''],
    'unknown field' => ['secret'],
    'text field' => ['customer'],
    'date field' => ['ordered_at'],
    'boolean field' => ['paid'],
])->throws(InvalidArgumentException::class);

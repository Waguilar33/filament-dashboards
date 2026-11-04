<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\Builder\Actions\CalculateBreakdown;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Tests\Fixtures\Record;
use Waguilar\FilamentDashboards\Tests\Fixtures\RecordsSource;
use Waguilar\FilamentDashboards\Tests\Fixtures\WebOrdersSource;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\DemoOrder;

/**
 * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
 * @return array{labels: list<string>, values: list<float|int>}
 */
function breakdown(string $group, Metric $metric = Metric::Count, ?string $field = null, array $filters = [], int $limit = 10, ?DataSource $source = null): array
{
    return app(CalculateBreakdown::class)->handle($source ?? new OrdersSource, user(), null, $group, $metric, $field, $filters, $limit);
}

beforeEach(function (): void {
    DemoOrder::factory()->create(['channel' => 'web', 'status' => 'paid', 'paid' => true, 'total' => 10]);
    DemoOrder::factory()->create(['channel' => 'web', 'status' => 'paid', 'paid' => true, 'total' => 20]);
    DemoOrder::factory()->create(['channel' => 'web', 'status' => 'pending', 'paid' => false, 'total' => 30]);
    DemoOrder::factory()->create(['channel' => 'store', 'status' => 'shipped', 'paid' => true, 'total' => 100]);
    DemoOrder::factory()->create(['channel' => 'phone', 'status' => 'cancelled', 'paid' => false, 'total' => 5]);
});

it('counts per group ordered by value descending with option labels', function (): void {
    expect(breakdown('channel'))->toBe([
        'labels' => ['Web', 'Phone', 'Store'],
        'values' => [3, 1, 1],
    ]);
});

it('orders by the aggregate of a number field', function (Metric $metric, array $values): void {
    expect(breakdown('channel', $metric, 'total')['values'])->toEqual($values);
})->with([
    'sum' => [Metric::Sum, [100, 60, 5]],
    'average' => [Metric::Average, [100, 20, 5]],
    'min' => [Metric::Min, [100, 10, 5]],
    'max' => [Metric::Max, [100, 30, 5]],
]);

it('limits the groups keeping the largest', function (): void {
    expect(breakdown('status', limit: 2))->toBe([
        'labels' => ['Paid', 'Cancelled'],
        'values' => [2, 1],
    ]);
});

it('labels booleans as translated yes and no', function (): void {
    expect(breakdown('paid')['labels'])->toBe(['Yes', 'No']);

    app()->setLocale('es');

    expect(breakdown('paid')['labels'])->toBe(['Sí', 'No']);
});

it('uses the raw value of a text field as the label', function (): void {
    DemoOrder::query()->update(['customer' => 'Ana']);

    expect(breakdown('customer'))->toBe(['labels' => ['Ana'], 'values' => [5]]);
});

it('applies the filters', function (): void {
    $filters = [[['field' => 'status', 'operator' => 'is', 'value' => 'paid']]];

    expect(breakdown('channel', filters: $filters))->toBe(['labels' => ['Web'], 'values' => [2]]);
});

it('stays inside the scoping of the source', function (): void {
    $filters = [[['field' => 'channel', 'operator' => 'is', 'value' => 'store']], [['field' => 'total', 'operator' => 'greater_than', 'value' => 0]]];

    expect(breakdown('channel', filters: $filters, source: new WebOrdersSource))->toBe(['labels' => ['Web'], 'values' => [3]]);
});

it('returns nothing for an empty set', function (): void {
    DemoOrder::query()->delete();

    expect(breakdown('channel'))->toBe(['labels' => [], 'values' => []]);
});

it('labels an empty group as empty', function (): void {
    Record::createTable();
    Record::query()->create(['kind' => 'a', 'amount' => 1]);
    Record::query()->create(['kind' => null, 'amount' => 2]);
    Record::query()->create(['kind' => null, 'amount' => 3]);

    expect(breakdown('kind', Metric::Sum, 'amount', source: new RecordsSource))->toEqual([
        'labels' => ['Empty', 'Alpha'],
        'values' => [5, 1],
    ]);
});

it('rejects an unknown group field and a metric without its number field', function (string $group, Metric $metric, ?string $field): void {
    breakdown($group, $metric, $field);
})->with([
    'unknown group' => ['secret', Metric::Count, null],
    'sum without field' => ['channel', Metric::Sum, null],
    'sum on text' => ['channel', Metric::Sum, 'customer'],
])->throws(InvalidArgumentException::class);

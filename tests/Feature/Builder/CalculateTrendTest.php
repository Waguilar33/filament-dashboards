<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateTrend;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Builder\Enums\TimeGrouping;
use Waguilar\FilamentDashboards\Tests\Fixtures\WebOrdersSource;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\DemoOrder;

/**
 * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
 * @return array{labels: list<string>, values: list<float|int>}
 */
function trend(TimeGrouping $grouping, string $from, string $to, Metric $metric = Metric::Count, ?string $field = null, array $filters = [], string $dateField = 'ordered_at', ?DataSource $source = null): array
{
    return app(CalculateTrend::class)->handle(
        $source ?? new OrdersSource,
        user(),
        null,
        $dateField,
        $grouping,
        CarbonImmutable::parse($from),
        CarbonImmutable::parse($to),
        $metric,
        $field,
        $filters,
    );
}

function order(string $orderedAt, int|float $total = 10, string $channel = 'web'): void
{
    DemoOrder::factory()->create(['ordered_at' => $orderedAt, 'total' => $total, 'channel' => $channel]);
}

it('fills every day of the range with zero', function (): void {
    order('2026-03-01 09:00:00');
    order('2026-03-01 21:00:00');
    order('2026-03-03 12:00:00');

    expect(trend(TimeGrouping::Day, '2026-03-01', '2026-03-05'))->toBe([
        'labels' => ['Mar 1', 'Mar 2', 'Mar 3', 'Mar 4', 'Mar 5'],
        'values' => [2, 0, 1, 0, 0],
    ]);
});

it('groups by week starting on Monday', function (): void {
    order('2026-03-02 00:00:00');
    order('2026-03-08 23:00:00');
    order('2026-03-16 10:00:00');

    expect(trend(TimeGrouping::Week, '2026-03-04', '2026-03-22'))->toBe([
        'labels' => ['Mar 2', 'Mar 9', 'Mar 16'],
        'values' => [2, 0, 1],
    ]);
});

it('groups by month', function (): void {
    order('2025-12-31 23:00:00');
    order('2026-01-15 10:00:00');
    order('2026-01-31 23:59:59');
    order('2026-03-01 00:00:00');

    expect(trend(TimeGrouping::Month, '2026-01-20', '2026-04-02'))->toBe([
        'labels' => ['Jan 2026', 'Feb 2026', 'Mar 2026', 'Apr 2026'],
        'values' => [2, 0, 1, 0],
    ]);
});

it('groups by year', function (): void {
    order('2024-06-01 10:00:00');
    order('2026-02-01 10:00:00');
    order('2026-11-01 10:00:00');

    expect(trend(TimeGrouping::Year, '2024-02-01', '2026-02-01'))->toBe([
        'labels' => ['2024', '2025', '2026'],
        'values' => [1, 0, 2],
    ]);
});

it('includes the whole last period of the range and nothing after it', function (): void {
    order('2026-03-05 23:59:59');
    order('2026-03-06 00:00:00');

    expect(trend(TimeGrouping::Day, '2026-03-05 10:00:00', '2026-03-05 11:00:00')['values'])->toBe([1]);
});

it('aggregates a number field per bucket', function (Metric $metric, array $expected): void {
    order('2026-03-01 10:00:00', 10);
    order('2026-03-01 11:00:00', 30);
    order('2026-03-03 10:00:00', 5);

    expect(trend(TimeGrouping::Day, '2026-03-01', '2026-03-03', $metric, 'total')['values'])->toEqual($expected);
})->with([
    'sum' => [Metric::Sum, [40, 0, 5]],
    'average' => [Metric::Average, [20, 0, 5]],
    'min' => [Metric::Min, [10, 0, 5]],
    'max' => [Metric::Max, [30, 0, 5]],
    'count' => [Metric::Count, [2, 0, 1]],
]);

it('applies the filters and stays inside the scoping of the source', function (): void {
    order('2026-03-01 10:00:00', 10, 'web');
    order('2026-03-01 11:00:00', 20, 'store');
    order('2026-03-02 11:00:00', 50, 'web');

    $or = [[['field' => 'total', 'operator' => 'greater_than', 'value' => 15]], [['field' => 'channel', 'operator' => 'is', 'value' => 'store']]];
    $web = [[['field' => 'channel', 'operator' => 'is', 'value' => 'web']]];

    expect(trend(TimeGrouping::Day, '2026-03-01', '2026-03-02', filters: $web)['values'])->toBe([1, 1])
        ->and(trend(TimeGrouping::Day, '2026-03-01', '2026-03-02', filters: $or, source: new WebOrdersSource)['values'])->toBe([0, 1]);
});

it('returns only zeros when nothing matches', function (): void {
    expect(trend(TimeGrouping::Month, '2026-01-01', '2026-02-01', Metric::Sum, 'total')['values'])->toBe([0, 0]);
});

it('rejects a date field that is not a date field of the source', function (string $field): void {
    trend(TimeGrouping::Day, '2026-03-01', '2026-03-02', dateField: $field);
})->with(['total', 'secret', 'created_at'])->throws(InvalidArgumentException::class);

it('rejects a metric that needs a number field', function (): void {
    trend(TimeGrouping::Day, '2026-03-01', '2026-03-02', Metric::Sum, 'customer');
})->throws(InvalidArgumentException::class);

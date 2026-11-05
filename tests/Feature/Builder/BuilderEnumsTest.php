<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\Builder\Enums\FieldType;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Builder\Enums\Operator;
use Waguilar\FilamentDashboards\Builder\Enums\TimeGrouping;
use Waguilar\FilamentDashboards\Builder\Field;
use Workbench\App\DataSources\OrdersSource;

it('offers the operators that fit each field type', function (FieldType $type, array $expected): void {
    expect(Operator::forType($type))->toBe($expected);
})->with([
    'number' => [FieldType::Number, [Operator::Is, Operator::IsNot, Operator::GreaterThan, Operator::LessThan, Operator::IsEmpty, Operator::IsNotEmpty]],
    'text' => [FieldType::Text, [Operator::Is, Operator::IsNot, Operator::Contains, Operator::IsEmpty, Operator::IsNotEmpty]],
    'date' => [FieldType::Date, [Operator::Is, Operator::IsNot, Operator::Before, Operator::After, Operator::IsEmpty, Operator::IsNotEmpty]],
    'boolean' => [FieldType::Boolean, [Operator::Is, Operator::IsNot]],
    'choice' => [FieldType::Choice, [Operator::Is, Operator::IsNot, Operator::IsEmpty, Operator::IsNotEmpty]],
]);

it('knows which operators need a value', function (): void {
    $withoutValue = array_filter(Operator::cases(), fn (Operator $operator): bool => ! $operator->requiresValue());

    expect(array_values($withoutValue))->toBe([Operator::IsEmpty, Operator::IsNotEmpty]);
});

it('needs a number field for every metric but count', function (): void {
    expect(Metric::Count->requiresNumberField())->toBeFalse()
        ->and(Metric::Sum->requiresNumberField())->toBeTrue()
        ->and(Metric::Average->requiresNumberField())->toBeTrue()
        ->and(Metric::Min->requiresNumberField())->toBeTrue()
        ->and(Metric::Max->requiresNumberField())->toBeTrue();
});

it('aggregates values', function (Metric $metric, int|float $expected): void {
    expect($metric->aggregate([4, 1, 7]))->toEqual($expected)
        ->and($metric->aggregate([]))->toBe(0);
})->with([
    'count' => [Metric::Count, 3],
    'sum' => [Metric::Sum, 12],
    'average' => [Metric::Average, 4],
    'min' => [Metric::Min, 1],
    'max' => [Metric::Max, 7],
]);

it('moves a date to the start of its period and on to the next one', function (TimeGrouping $grouping, string $start, string $next): void {
    $date = CarbonImmutable::parse('2026-03-11 15:30:00');

    expect($grouping->start($date)->toDateTimeString())->toBe($start)
        ->and($grouping->next($grouping->start($date))->toDateTimeString())->toBe($next);
})->with([
    'day' => [TimeGrouping::Day, '2026-03-11 00:00:00', '2026-03-12 00:00:00'],
    'week' => [TimeGrouping::Week, '2026-03-09 00:00:00', '2026-03-16 00:00:00'],
    'month' => [TimeGrouping::Month, '2026-03-01 00:00:00', '2026-04-01 00:00:00'],
    'year' => [TimeGrouping::Year, '2026-01-01 00:00:00', '2027-01-01 00:00:00'],
]);

it('translates every enum label in both languages', function (string $locale): void {
    app()->setLocale($locale);

    $labels = collect([FieldType::class, Metric::class, Operator::class, TimeGrouping::class])
        ->flatMap(fn (string $enum): array => array_map(fn (UnitEnum $case): string => $case->getLabel(), $enum::cases()));

    expect($labels->filter(fn (string $label): bool => str_starts_with($label, 'filament-dashboards::')))->toBeEmpty()
        ->and($labels)->not->toBeEmpty();
})->with(['en', 'es']);

it('labels choice options and booleans', function (): void {
    $status = Field::choice('status', 'Status', ['paid' => 'Paid']);

    expect($status->valueLabel('paid'))->toBe('Paid')
        ->and($status->valueLabel('other'))->toBe('other')
        ->and(Field::boolean('paid', 'Paid')->valueLabel(1))->toBe('Yes')
        ->and(Field::boolean('paid', 'Paid')->valueLabel(false))->toBe('No')
        ->and(Field::number('total', 'Total')->valueLabel(12))->toBe('12');
});

it('describes the demo orders source', function (): void {
    $source = new OrdersSource;

    expect($source->key())->toBe('orders')
        ->and($source->group())->toBe('Shop')
        ->and($source->label())->toBe('Orders')
        ->and(array_map(fn (Field $field): string => $field->name, $source->fields()))
        ->toBe(['number', 'customer', 'status', 'channel', 'total', 'items_count', 'paid', 'ordered_at']);
});

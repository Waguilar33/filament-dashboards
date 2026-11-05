<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\Builder\Actions\CalculateFigureTable;
use Waguilar\FilamentDashboards\Builder\Enums\Arithmetic;

/**
 * @param  array<string, mixed>  $overrides
 * @return array{key: string, figure: int|float|null, left: array{column: ?string, number: int|float|null}, operator: ?Arithmetic, right: array{column: ?string, number: int|float|null}, share_of: ?string, blank_rows: list<string>}
 */
function figureColumn(string $key, int|float|null $figure, array $overrides = []): array
{
    return [
        'key' => $key,
        'figure' => $figure,
        'left' => ['column' => null, 'number' => null],
        'operator' => null,
        'right' => ['column' => null, 'number' => null],
        'share_of' => null,
        'blank_rows' => [],
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array{key: string, figure: int|float|null, left: array{column: ?string, number: int|float|null}, operator: ?Arithmetic, right: array{column: ?string, number: int|float|null}, share_of: ?string, blank_rows: list<string>}
 */
function calculationColumn(string $key, string|int|float $left, Arithmetic $operator, string|int|float $right, array $overrides = []): array
{
    $operand = fn (string|int|float $value): array => is_string($value) ? ['column' => $value, 'number' => null] : ['column' => null, 'number' => $value];

    return figureColumn($key, null, ['left' => $operand($left), 'operator' => $operator, 'right' => $operand($right), ...$overrides]);
}

/**
 * @return array{key: string, label: string, operator: ?Arithmetic, number: int|float|null}
 */
function baseRow(): array
{
    return ['key' => 'base', 'label' => 'Total', 'operator' => null, 'number' => null];
}

it('calculates columns in order from the columns before them', function (): void {
    $table = app(CalculateFigureTable::class)->handle([
        figureColumn('sales', 1000),
        figureColumn('discounts', 100),
        calculationColumn('net', 'sales', Arithmetic::Subtract, 'discounts'),
        calculationColumn('double', 'net', Arithmetic::Multiply, 2),
        calculationColumn('plus', 'double', Arithmetic::Add, 'later'),
        figureColumn('later', 5),
    ], [baseRow()]);

    expect($table['rows'][0]['values'])->toBe(['sales' => 1000, 'discounts' => 100, 'net' => 900, 'double' => 1800, 'plus' => null, 'later' => 5]);
});

it('leaves a cell empty on division by zero', function (): void {
    $table = app(CalculateFigureTable::class)->handle([
        figureColumn('sales', 1000),
        figureColumn('none', 0),
        calculationColumn('ratio', 'sales', Arithmetic::Divide, 'none', ['share_of' => 'none']),
    ], [baseRow(), ['key' => 'half', 'label' => 'Half', 'operator' => Arithmetic::Divide, 'number' => 0]]);

    expect($table['rows'][0]['values']['ratio'])->toBeNull()
        ->and($table['rows'][1]['values'])->toBe(['sales' => null, 'none' => null, 'ratio' => null])
        ->and($table['shares']['ratio'])->toBeNull();
});

it('applies each further row to the base figures and leaves a column blank where asked', function (): void {
    $table = app(CalculateFigureTable::class)->handle([
        figureColumn('sales', 1120),
        figureColumn('paid', 800, ['blank_rows' => ['net']]),
    ], [
        baseRow(),
        ['key' => 'net', 'label' => 'Net', 'operator' => Arithmetic::Divide, 'number' => 1.12],
        ['key' => 'more', 'label' => 'More', 'operator' => Arithmetic::Add, 'number' => 10],
    ]);

    expect(array_column($table['rows'], 'label'))->toBe(['Total', 'Net', 'More'])
        ->and($table['rows'][1]['values']['sales'])->toEqualWithDelta(1000, 0.0001)
        ->and($table['rows'][1]['values']['paid'])->toBeNull()
        ->and($table['rows'][2]['values'])->toBe(['sales' => 1130, 'paid' => 810]);
});

it('works out each column as a share of the column it names', function (): void {
    $table = app(CalculateFigureTable::class)->handle([
        figureColumn('sales', 1000, ['share_of' => 'sales']),
        figureColumn('card', 250, ['share_of' => 'sales']),
        figureColumn('cash', 750),
    ], [baseRow()]);

    expect($table['shares'])->toBe(['sales' => 100, 'card' => 25, 'cash' => null]);
});

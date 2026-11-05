<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Waguilar\FilamentDashboards\Builder\Enums\Arithmetic;

class CalculateFigureTable
{
    /**
     * @param  list<array{key: string, figure: int|float|null, left: array{column: ?string, number: int|float|null}, operator: ?Arithmetic, right: array{column: ?string, number: int|float|null}, share_of: ?string, blank_rows: list<string>}>  $columns
     * @param  list<array{key: string, label: string, operator: ?Arithmetic, number: int|float|null}>  $rows
     * @return array{shares: array<string, int|float|null>, rows: list<array{key: string, label: string, values: array<string, int|float|null>}>}
     */
    public function handle(array $columns, array $rows): array
    {
        $base = [];

        foreach ($columns as $column) {
            $base[$column['key']] = $column['operator'] === null
                ? $column['figure']
                : $column['operator']->apply($this->operand($column['left'], $base), $this->operand($column['right'], $base));
        }

        $shares = [];

        foreach ($columns as $column) {
            $shares[$column['key']] = $column['share_of'] === null
                ? null
                : Arithmetic::Divide->apply(Arithmetic::Multiply->apply($base[$column['key']], 100), $base[$column['share_of']] ?? null);
        }

        $lines = [];

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[$column['key']] = match (true) {
                    in_array($row['key'], $column['blank_rows'], true) => null,
                    $row['operator'] === null => $base[$column['key']],
                    default => $row['operator']->apply($base[$column['key']], $row['number']),
                };
            }

            $lines[] = ['key' => $row['key'], 'label' => $row['label'], 'values' => $values];
        }

        return ['shares' => $shares, 'rows' => $lines];
    }

    /**
     * @param  array{column: ?string, number: int|float|null}  $operand
     * @param  array<string, int|float|null>  $earlier
     */
    protected function operand(array $operand, array $earlier): int|float|null
    {
        return $operand['column'] === null ? $operand['number'] : ($earlier[$operand['column']] ?? null);
    }
}

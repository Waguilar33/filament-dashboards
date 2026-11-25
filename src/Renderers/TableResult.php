<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Renderers;

final readonly class TableResult
{
    /**
     * @param  list<TableColumn>  $columns
     * @param  list<TableRow>  $rows
     */
    public function __construct(
        public array $columns,
        public array $rows = [],
        public ?TableRow $totals = null,
        public bool $needsAttention = false,
    ) {}

    /**
     * @param  list<TableColumn>  $columns
     */
    public static function needingAttention(array $columns): self
    {
        return new self($columns, needsAttention: true);
    }

    public function isEmpty(): bool
    {
        return $this->rows === [] && ! $this->totals instanceof TableRow;
    }

    /**
     * @return array{columns: list<array{key: string, label: string, type: string, alignment: string}>, rows: list<array{values: array<string, bool|int|float|string|null>, display: array<string, string|null>, drillDown: array<string, mixed>|null}>, totals: array{values: array<string, bool|int|float|string|null>, display: array<string, string|null>, drillDown: array<string, mixed>|null}|null, needsAttention: bool}
     */
    public function toArray(): array
    {
        return [
            'columns' => array_map(fn (TableColumn $column): array => $column->toArray(), $this->columns),
            'rows' => array_map(fn (TableRow $row): array => $row->toArray(), $this->rows),
            'totals' => $this->totals?->toArray(),
            'needsAttention' => $this->needsAttention,
        ];
    }
}

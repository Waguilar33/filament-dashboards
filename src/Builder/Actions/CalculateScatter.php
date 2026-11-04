<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\FieldType;

class CalculateScatter
{
    public function __construct(
        protected ApplyFilters $applyFilters,
        protected ResolveField $resolveField,
        protected ParseNumber $parseNumber,
    ) {}

    /**
     * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
     * @return list<array{x: float|int, y: float|int}>
     */
    public function handle(
        DataSource $source,
        Model $user,
        ?Model $tenant,
        string $xField,
        string $yField,
        array $filters,
        int $limit = 500,
    ): array {
        $x = $this->resolveField->handle($source, $xField, FieldType::Number);
        $y = $this->resolveField->handle($source, $yField, FieldType::Number);

        $query = $this->applyFilters->handle($source->query($user, $tenant), $source, $filters);
        $xColumn = $query->qualifyColumn($x->name);
        $yColumn = $query->qualifyColumn($y->name);

        return array_values($query
            ->whereNotNull($xColumn)
            ->whereNotNull($yColumn)
            ->limit($limit)
            ->toBase()
            ->get([$xColumn, $yColumn])
            ->map(fn (object $row): array => (array) $row)
            ->map(fn (array $row): array => [
                'x' => $this->parseNumber->handle($row[$x->name]),
                'y' => $this->parseNumber->handle($row[$y->name]),
            ])
            ->all());
    }
}

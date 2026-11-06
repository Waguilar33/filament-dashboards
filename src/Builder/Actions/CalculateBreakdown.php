<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\AggregateExpression;
use Waguilar\FilamentDashboards\Builder\AggregateIsNull;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Dates\DateRange;

class CalculateBreakdown
{
    public function __construct(
        protected ApplyFilters $applyFilters,
        protected ResolveField $resolveField,
        protected ResolveMetricField $resolveMetricField,
        protected ParseNumber $parseNumber,
        protected ResolveColumn $resolveColumn,
    ) {}

    /**
     * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
     * @return array{labels: list<string>, values: list<float|int>}
     */
    public function handle(
        DataSource $source,
        Model $user,
        ?Model $tenant,
        string $groupField,
        Metric $metric,
        ?string $field,
        array $filters,
        int $limit = 10,
        ?string $dateField = null,
        ?DateRange $dates = null,
    ): array {
        $group = $this->resolveField->handle($source, $groupField);
        $numberField = $this->resolveMetricField->handle($source, $metric, $field);

        $query = $this->applyFilters->handle($source->query($user, $tenant), $source, $filters, $dateField, $dates);
        $groupColumn = $this->resolveColumn->handle($query, $group);
        $numberColumn = $numberField === null ? null : $this->resolveColumn->handle($query, $numberField);
        $rows = $query
            ->reorder()
            ->select("{$groupColumn} as {$group->alias()}")
            ->addSelect(new AggregateExpression($metric->sqlFunction(), $numberColumn, 'aggregate'))
            ->groupBy($groupColumn)
            ->orderBy(new AggregateIsNull($metric->sqlFunction(), $numberColumn))
            ->orderByDesc('aggregate')
            ->orderBy($groupColumn)
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => (array) $row);

        return [
            'labels' => array_values($rows
                ->map(fn (array $row): string => blank($row[$group->alias()]) ? trans('filament-dashboards::builder.empty') : $group->valueLabel($row[$group->alias()]))
                ->all()),
            'values' => array_values($rows
                ->map(fn (array $row): int|float => $numberField === null
                    ? $this->parseNumber->handle($row['aggregate'])
                    : $numberField->fromStored($this->parseNumber->handle($row['aggregate'])))
                ->all()),
        ];
    }
}

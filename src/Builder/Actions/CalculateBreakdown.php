<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\AggregateExpression;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;

class CalculateBreakdown
{
    public function __construct(
        protected ApplyFilters $applyFilters,
        protected ResolveField $resolveField,
        protected ResolveMetricField $resolveMetricField,
        protected ParseNumber $parseNumber,
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
    ): array {
        $group = $this->resolveField->handle($source, $groupField);
        $numberField = $this->resolveMetricField->handle($source, $metric, $field);

        $query = $this->applyFilters->handle($source->query($user, $tenant), $source, $filters);
        $groupColumn = $query->qualifyColumn($group->name);
        $function = match ($metric) {
            Metric::Count => 'count',
            Metric::Sum => 'sum',
            Metric::Average => 'avg',
            Metric::Min => 'min',
            Metric::Max => 'max',
        };

        $rows = $query
            ->reorder()
            ->select($groupColumn)
            ->addSelect(new AggregateExpression($function, $numberField === null ? null : $query->qualifyColumn($numberField->name), 'aggregate'))
            ->groupBy($groupColumn)
            ->orderByDesc('aggregate')
            ->orderBy($groupColumn)
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => (array) $row);

        return [
            'labels' => array_values($rows
                ->map(fn (array $row): string => blank($row[$group->name]) ? trans('filament-dashboards::builder.empty') : $group->valueLabel($row[$group->name]))
                ->all()),
            'values' => array_values($rows
                ->map(fn (array $row): int|float => $this->parseNumber->handle($row['aggregate']))
                ->all()),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\AggregateExpression;
use Waguilar\FilamentDashboards\Builder\AggregateIsNull;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Builder\Field;
use Waguilar\FilamentDashboards\Dates\DateRange;

class CalculateSummary
{
    public function __construct(
        protected ApplyFilters $applyFilters,
        protected ResolveField $resolveField,
        protected ResolveMetricField $resolveMetricField,
        protected ParseNumber $parseNumber,
        protected ResolveColumn $resolveColumn,
    ) {}

    /**
     * @param  non-empty-list<array{metric: Metric, field: ?string}>  $metrics
     * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
     * @return array{rows: list<array{group: string, key: bool|float|int|string|null, values: list<int|float>}>, totals: list<int|float>}
     */
    public function handle(
        DataSource $source,
        Model $user,
        ?Model $tenant,
        string $groupField,
        array $metrics,
        array $filters,
        int $limit = 20,
        ?string $dateField = null,
        ?DateRange $dates = null,
    ): array {
        $group = $this->resolveField->handle($source, $groupField);
        $numberFields = array_map(fn (array $metric): ?Field => $this->resolveMetricField->handle($source, $metric['metric'], $metric['field']), $metrics);

        $query = $this->applyFilters->handle($source->query($user, $tenant), $source, $filters, $dateField, $dates)->reorder();
        $groupColumn = $this->resolveColumn->handle($query, $group);

        $rows = $this->aggregates(clone $query, $metrics, $numberFields)
            ->addSelect("{$groupColumn} as {$group->alias()}")
            ->groupBy($groupColumn)
            ->orderBy(new AggregateIsNull($metrics[0]['metric']->sqlFunction(), $numberFields[0] === null ? null : $this->resolveColumn->handle($query, $numberFields[0])))
            ->orderByDesc('aggregate_0')
            ->orderBy($groupColumn)
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => (array) $row);

        $totals = (array) $this->aggregates($query, $metrics, $numberFields)->toBase()->first();

        return [
            'rows' => array_values($rows->map(fn (array $row): array => [
                'group' => blank($row[$group->alias()]) ? trans('filament-dashboards::builder.empty') : $group->valueLabel($row[$group->alias()]),
                'key' => is_scalar($row[$group->alias()]) && filled($row[$group->alias()]) ? $row[$group->alias()] : null,
                'values' => $this->values($row, $numberFields),
            ])->all()),
            'totals' => $this->values($totals, $numberFields),
        ];
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  non-empty-list<array{metric: Metric, field: ?string}>  $metrics
     * @param  list<?Field>  $numberFields
     * @return Builder<covariant Model>
     */
    private function aggregates(Builder $query, array $metrics, array $numberFields): Builder
    {
        $query->select([]);

        foreach ($metrics as $index => $metric) {
            $query->addSelect(new AggregateExpression(
                $metric['metric']->sqlFunction(),
                $numberFields[$index] === null ? null : $this->resolveColumn->handle($query, $numberFields[$index]),
                "aggregate_{$index}",
            ));
        }

        return $query;
    }

    /**
     * @param  array<mixed>  $row
     * @param  list<?Field>  $numberFields
     * @return list<int|float>
     */
    private function values(array $row, array $numberFields): array
    {
        $values = [];

        foreach ($numberFields as $index => $field) {
            $value = $this->parseNumber->handle($row["aggregate_{$index}"] ?? null);
            $values[] = $field === null ? $value : $field->fromStored($value);
        }

        return $values;
    }
}

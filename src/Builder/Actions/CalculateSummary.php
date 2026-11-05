<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\AggregateExpression;
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
    ) {}

    /**
     * @param  non-empty-list<array{metric: Metric, field: ?string}>  $metrics
     * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
     * @return array{rows: list<array{group: string, values: list<int|float>}>, totals: list<int|float>}
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
        $groupColumn = $query->qualifyColumn($group->name);

        $rows = $this->aggregates(clone $query, $metrics, $numberFields)
            ->addSelect($groupColumn)
            ->groupBy($groupColumn)
            ->orderByDesc('aggregate_0')
            ->orderBy($groupColumn)
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => (array) $row);

        $totals = (array) $this->aggregates($query, $metrics, $numberFields)->toBase()->first();

        return [
            'rows' => array_values($rows->map(fn (array $row): array => [
                'group' => blank($row[$group->name]) ? trans('filament-dashboards::builder.empty') : $group->valueLabel($row[$group->name]),
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
                $numberFields[$index] === null ? null : $query->qualifyColumn($numberFields[$index]->name),
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

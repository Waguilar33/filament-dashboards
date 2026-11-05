<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Dates\DateRange;

class CalculateMetric
{
    public function __construct(
        protected ApplyFilters $applyFilters,
        protected ResolveMetricField $resolveMetricField,
        protected ParseNumber $parseNumber,
        protected ResolveColumn $resolveColumn,
    ) {}

    /**
     * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
     */
    public function handle(DataSource $source, Model $user, ?Model $tenant, Metric $metric, ?string $field, array $filters, ?string $dateField = null, ?DateRange $dates = null): float|int
    {
        $numberField = $this->resolveMetricField->handle($source, $metric, $field);
        $query = $this->applyFilters->handle($source->query($user, $tenant), $source, $filters, $dateField, $dates);
        $column = $numberField === null ? '*' : $this->resolveColumn->handle($query, $numberField);

        $value = $this->parseNumber->handle(match ($metric) {
            Metric::Count => $query->count(),
            Metric::Sum => $query->sum($column),
            Metric::Average => $query->avg($column),
            Metric::Min => $query->min($column),
            Metric::Max => $query->max($column),
        });

        return $numberField?->fromStored($value) ?? $value;
    }
}

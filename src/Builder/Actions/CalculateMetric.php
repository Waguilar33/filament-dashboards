<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;

class CalculateMetric
{
    public function __construct(
        protected ApplyFilters $applyFilters,
        protected ResolveMetricField $resolveMetricField,
        protected ParseNumber $parseNumber,
    ) {}

    /**
     * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
     */
    public function handle(DataSource $source, Model $user, ?Model $tenant, Metric $metric, ?string $field, array $filters): float|int
    {
        $numberField = $this->resolveMetricField->handle($source, $metric, $field);
        $query = $this->applyFilters->handle($source->query($user, $tenant), $source, $filters);
        $column = $numberField === null ? '*' : $query->qualifyColumn($numberField->name);

        return $this->parseNumber->handle(match ($metric) {
            Metric::Count => $query->count(),
            Metric::Sum => $query->sum($column),
            Metric::Average => $query->avg($column),
            Metric::Min => $query->min($column),
            Metric::Max => $query->max($column),
        });
    }
}

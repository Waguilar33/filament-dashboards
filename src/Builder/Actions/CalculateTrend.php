<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\FieldType;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Builder\Enums\TimeGrouping;

class CalculateTrend
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
        string $dateField,
        TimeGrouping $grouping,
        CarbonInterface $from,
        CarbonInterface $to,
        Metric $metric,
        ?string $field,
        array $filters,
    ): array {
        $date = $this->resolveField->handle($source, $dateField, FieldType::Date);
        $numberField = $this->resolveMetricField->handle($source, $metric, $field);

        $start = $grouping->start(CarbonImmutable::instance($from)->setTimezone(date_default_timezone_get()));
        $end = $grouping->next($grouping->start(CarbonImmutable::instance($to)->setTimezone(date_default_timezone_get())));

        $query = $this->applyFilters->handle($source->query($user, $tenant), $source, $filters);
        $dateColumn = $this->resolveColumn->handle($query, $date);
        $columns = $numberField === null ? ["{$dateColumn} as bucket_date"] : ["{$dateColumn} as bucket_date", $this->resolveColumn->handle($query, $numberField).' as bucket_value'];

        $perBucket = $query
            ->where($dateColumn, '>=', $start)
            ->where($dateColumn, '<', $end)
            ->toBase()
            ->get($columns)
            ->map(fn (object $row): array => (array) $row)
            ->map(function (array $row) use ($numberField, $grouping): ?array {
                $when = $row['bucket_date'];
                $value = $numberField === null ? 1 : $row['bucket_value'];

                if (! is_string($when) || $value === null) {
                    return null;
                }

                return [
                    'bucket' => $grouping->start(CarbonImmutable::parse($when))->format('Y-m-d'),
                    'value' => $numberField === null ? 1 : $numberField->fromStored($this->parseNumber->handle($value)),
                ];
            })
            ->filter()
            ->mapToGroups(fn (array $row): array => [$row['bucket'] => $row['value']]);

        $buckets = [];

        for ($cursor = $start; $cursor < $end; $cursor = $grouping->next($cursor)) {
            $buckets[] = $cursor;
        }

        return [
            'labels' => array_map(fn (CarbonImmutable $bucket): string => $grouping->label($bucket), $buckets),
            'values' => array_map(
                fn (CarbonImmutable $bucket): int|float => $metric->aggregate(array_values($perBucket->get($bucket->format('Y-m-d'))?->all() ?? [])),
                $buckets,
            ),
        ];
    }
}

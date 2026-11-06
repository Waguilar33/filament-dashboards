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
use Waguilar\FilamentDashboards\Builder\Field;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

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
     * @return array{labels: list<string>, values: list<float|int>, series: list<array{label: string, values: list<float|int>}>}
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
        ?string $splitField = null,
        int $seriesLimit = 10,
    ): array {
        $date = $this->resolveField->handle($source, $dateField, FieldType::Date);
        $numberField = $this->resolveMetricField->handle($source, $metric, $field);
        $split = blank($splitField) ? null : $this->resolveField->handle($source, $splitField);

        $timezone = FilamentDashboardsPlugin::get()->getTimezone();
        $appTimezone = date_default_timezone_get();
        $from = CarbonImmutable::instance($from)->setTimezone($timezone)->startOfDay();
        $to = CarbonImmutable::instance($to)->setTimezone($timezone)->endOfDay();

        $query = $this->applyFilters->handle($source->query($user, $tenant), $source, $filters);
        $dateColumn = $this->resolveColumn->handle($query, $date);
        $columns = $numberField === null ? ["{$dateColumn} as bucket_date"] : ["{$dateColumn} as bucket_date", $this->resolveColumn->handle($query, $numberField).' as bucket_value'];

        if ($split !== null) {
            $columns[] = $this->resolveColumn->handle($query, $split).' as bucket_split';
        }

        $rows = $query
            ->whereBetween($dateColumn, $date->dateOnly
                ? [$from->toDateString(), $to->toDateString()]
                : [$from->setTimezone($appTimezone), $to->setTimezone($appTimezone)])
            ->toBase()
            ->get($columns)
            ->map(fn (object $row): array => (array) $row)
            ->map(function (array $row) use ($numberField, $grouping, $date, $timezone, $appTimezone): ?array {
                $when = $row['bucket_date'];
                $value = $numberField === null ? 1 : $row['bucket_value'];

                if (! is_string($when) || $value === null) {
                    return null;
                }

                $moment = $date->dateOnly
                    ? CarbonImmutable::parse(substr($when, 0, 10), $timezone)
                    : CarbonImmutable::parse($when, $appTimezone)->setTimezone($timezone);

                return [
                    'bucket' => $grouping->start($moment)->format('Y-m-d'),
                    'value' => $numberField === null ? 1 : $numberField->fromStored($this->parseNumber->handle($value)),
                    'split' => is_scalar($row['bucket_split'] ?? null) ? (string) $row['bucket_split'] : '',
                ];
            })
            ->filter()
            ->values()
            ->all();
        $rows = array_values($rows);

        $buckets = [];

        for ($cursor = $grouping->start($from); $cursor <= $to; $cursor = $grouping->next($cursor)) {
            $buckets[] = $cursor;
        }

        return [
            'labels' => array_map(fn (CarbonImmutable $bucket): string => $grouping->label($bucket), $buckets),
            'values' => $this->bucketValues($rows, $buckets, $metric),
            'series' => $split === null ? [] : $this->series($rows, $split, $metric, $buckets, $seriesLimit),
        ];
    }

    /**
     * @param  list<array{bucket: string, value: int|float, split: string}>  $rows
     * @param  list<CarbonImmutable>  $buckets
     * @return list<array{label: string, values: list<int|float>}>
     */
    protected function series(array $rows, Field $split, Metric $metric, array $buckets, int $limit): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[$row['split']][] = $row;
        }

        ksort($groups);
        uasort($groups, fn (array $a, array $b): int => $metric->aggregate(array_column($b, 'value')) <=> $metric->aggregate(array_column($a, 'value')));

        $shown = count($groups) > $limit ? array_slice($groups, 0, $limit - 1, preserve_keys: true) : $groups;
        $series = [];

        foreach ($shown as $value => $group) {
            $series[] = ['label' => (string) $value === '' ? (string) trans('filament-dashboards::builder.empty') : $split->valueLabel($value), 'values' => $this->bucketValues($group, $buckets, $metric)];
        }

        $rest = array_diff_key($groups, $shown);

        if ($rest !== []) {
            $series[] = ['label' => (string) trans('filament-dashboards::builder.other'), 'values' => $this->bucketValues(array_merge(...array_values($rest)), $buckets, $metric)];
        }

        return $series;
    }

    /**
     * @param  list<array{bucket: string, value: int|float, split: string}>  $rows
     * @param  list<CarbonImmutable>  $buckets
     * @return list<int|float>
     */
    protected function bucketValues(array $rows, array $buckets, Metric $metric): array
    {
        $byBucket = [];

        foreach ($rows as $row) {
            $byBucket[$row['bucket']][] = $row['value'];
        }

        return array_map(fn (CarbonImmutable $bucket): int|float => $metric->aggregate($byBucket[$bucket->format('Y-m-d')] ?? []), $buckets);
    }
}

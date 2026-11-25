<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Renderers\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateSummary;
use Waguilar\FilamentDashboards\Builder\Actions\FetchRows;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\FieldType;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Builder\Field;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Renderers\ColumnType;
use Waguilar\FilamentDashboards\Renderers\TableColumn;
use Waguilar\FilamentDashboards\Renderers\TableResult;
use Waguilar\FilamentDashboards\Renderers\TableRow;
use Waguilar\FilamentDashboards\Widgets\Concerns\DrillsDownToRecords;
use Waguilar\FilamentDashboards\Widgets\Concerns\ExportsData;
use Waguilar\FilamentDashboards\Widgets\Concerns\InteractsWithBuilderWidget;

use function Filament\Support\locale_has_pluralization;

trait InteractsWithBuilderTable
{
    use DrillsDownToRecords;
    use ExportsData;
    use InteractsWithBuilderWidget;

    protected ?TableResult $tableResult = null;

    protected function tableResult(): TableResult
    {
        if ($this->tableResult instanceof TableResult) {
            return $this->tableResult;
        }

        $config = $this->widgetConfig();
        $result = $this->widgetType() === BuilderWidgetType::Summary->value ? $this->summaryResult($config) : $this->rowsResult($config);
        $this->needsAttention = $result->needsAttention;

        return $this->tableResult = $result;
    }

    protected function builderHeading(): ?string
    {
        return $this->builderWidget()?->name;
    }

    protected function builderDescription(): ?string
    {
        return $this->ownRangeLabel([$this->widgetConfig()]);
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function builderHeaderActions(): array
    {
        return [$this->widgetDatesAction(), $this->exportAction()];
    }

    protected function builderEmptyStateHeading(): string
    {
        $label = __('filament-tables::table.default_model_label');

        return __('filament-tables::table.empty.heading', ['model' => locale_has_pluralization() ? Str::plural($label) : $label]);
    }

    protected function builderEmptyStateDescription(): ?string
    {
        return $this->tableResult()->needsAttention ? $this->needsAttentionText() : null;
    }

    protected function builderPollingInterval(): ?string
    {
        return $this->getPollingInterval();
    }

    protected function widgetDatesChanged(): void
    {
        $this->tableResult = null;
        $this->tableResultChanged();
    }

    protected function tableResultChanged(): void {}

    /**
     * @return array{0: list<string>, 1: list<list<bool|float|int|string|null>>}
     */
    protected function exportTable(): array
    {
        $result = $this->tableResult();
        $rows = $result->totals === null ? $result->rows : [...$result->rows, $result->totals];

        return [
            array_map(fn (TableColumn $column): string => $column->label, $result->columns),
            array_map(fn (TableRow $row): array => array_map(fn (TableColumn $column): bool|float|int|string|null => $row->value($column->key), $result->columns), $rows),
        ];
    }

    /**
     * @return list<string>
     */
    protected function exportSourceKeys(): array
    {
        $source = $this->configString($this->widgetConfig(), 'source');

        return $source === null ? [] : [$source];
    }

    /**
     * @param  array<mixed>  $arguments
     * @return array{part: array<mixed>, rule: ?array{field: string, operator: string, value?: mixed}, title: string}|null
     */
    protected function drillDownTarget(array $arguments): ?array
    {
        $config = $this->widgetConfig();
        $group = $this->configString($config, 'group_field');
        $key = $arguments['key'] ?? null;

        if ($this->widgetType() !== BuilderWidgetType::Summary->value || $group === null) {
            return null;
        }

        return [
            'part' => $config,
            'rule' => is_scalar($key) && filled($key)
                ? ['field' => $group, 'operator' => 'is', 'value' => $key]
                : ['field' => $group, 'operator' => 'is_empty'],
            'title' => is_string($arguments['label'] ?? null) ? $arguments['label'] : '',
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function summaryResult(array $config): TableResult
    {
        $source = $this->source($config['source'] ?? null);
        $metrics = $this->summaryMetrics($config);
        $groupField = $source === null ? null : $this->field($source, $this->configString($config, 'group_field'));
        $columns = [new TableColumn('group', $groupField->label ?? '')];
        $fields = [];

        foreach ($metrics as $index => $metric) {
            $field = $fields[$index] = $source === null ? null : $this->field($source, $metric['field']);
            $columns[] = new TableColumn("value_{$index}", $metric['label'] ?? implode(' · ', array_filter([$metric['metric']->getLabel(), $field?->label])), ColumnType::Number, Alignment::End);

            if ($metric['percent']) {
                $columns[] = new TableColumn("percent_{$index}", __('filament-dashboards::dashboards.builder.summary.percent'), ColumnType::Percent, Alignment::End);
            }
        }

        if ($source === null || $metrics === []) {
            return new TableResult($columns);
        }

        $limit = $config['limit'] ?? 20;

        try {
            $summary = app(CalculateSummary::class)->handle(
                $source,
                $this->viewer(),
                $this->tenant(),
                $this->configString($config, 'group_field') ?? '',
                array_map(fn (array $metric): array => ['metric' => $metric['metric'], 'field' => $metric['field']], $metrics),
                $this->filters($config),
                is_numeric($limit) ? max(1, min(FilamentDashboardsPlugin::get()->getBuilderLimit('summary'), (int) $limit)) : 20,
                $this->configString($config, 'dates_field'),
                $this->dashboardDates($config),
            );
        } catch (InvalidArgumentException) {
            return TableResult::needingAttention($columns);
        }

        $drills = $this->canDrillDown($config['source'] ?? null);

        return new TableResult(
            $columns,
            array_map(fn (array $line): TableRow => $this->summaryRow($line['group'], $line['values'], $summary['totals'], $metrics, $fields, $drills ? ['key' => $line['key'], 'label' => $line['group']] : null), $summary['rows']),
            (bool) ($config['total_row'] ?? false) ? $this->summaryRow(__('filament-dashboards::dashboards.builder.summary.total'), $summary['totals'], $summary['totals'], $metrics, $fields, null) : null,
        );
    }

    /**
     * @param  list<int|float>  $values
     * @param  list<int|float>  $totals
     * @param  non-empty-list<array{metric: Metric, field: ?string, label: ?string, percent: bool}>  $metrics
     * @param  array<int, ?Field>  $fields
     * @param  array<string, mixed>|null  $drillDown
     */
    protected function summaryRow(string $group, array $values, array $totals, array $metrics, array $fields, ?array $drillDown): TableRow
    {
        $row = ['group' => $group];
        $display = ['group' => $group];

        foreach ($values as $index => $value) {
            $row["value_{$index}"] = $value;
            $display["value_{$index}"] = ($fields[$index] ?? null)?->format($value) ?? $this->formatNumber($value);

            if ($metrics[$index]['percent']) {
                $share = $totals[$index] == 0 ? 0 : $value / $totals[$index] * 100;
                $row["percent_{$index}"] = $share;
                $display["percent_{$index}"] = FilamentDashboardsPlugin::get()->formatNumber($share, 2).'%';
            }
        }

        return new TableRow($row, $display, $drillDown);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array{metric: Metric, field: ?string, label: ?string, percent: bool}>
     */
    protected function summaryMetrics(array $config): array
    {
        $metrics = [];

        foreach (is_array($config['metrics'] ?? null) ? $config['metrics'] : [] as $metric) {
            if (! is_array($metric)) {
                continue;
            }

            $kind = Metric::tryFrom($this->configString($metric, 'metric') ?? '') ?? Metric::Count;
            $metrics[] = [
                'metric' => $kind,
                'field' => $this->configString($metric, 'field'),
                'label' => $this->configString($metric, 'label'),
                'percent' => $kind->sharesOfTotal() && (bool) ($metric['percent'] ?? false),
            ];
        }

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function rowsResult(array $config): TableResult
    {
        $source = $this->source($config['source'] ?? null);
        $names = array_values(array_filter(is_array($config['columns'] ?? null) ? $config['columns'] : [], is_string(...)));
        $fields = collect($source?->fields() ?? [])->keyBy(fn (Field $field): string => $field->name);
        $columns = [];
        $shown = [];

        foreach ($names as $name) {
            $field = $fields->get($name);

            if ($field instanceof Field) {
                $shown[] = $field;
                $columns[] = new TableColumn($field->alias(), $field->label, match ($field->type) {
                    FieldType::Number => ColumnType::Number,
                    FieldType::Date => ColumnType::Date,
                    default => ColumnType::Text,
                });
            }
        }

        if (! $source instanceof DataSource) {
            return new TableResult($columns);
        }

        $limit = $config['limit'] ?? 10;

        try {
            $records = app(FetchRows::class)->handle(
                $source,
                $this->viewer(),
                $this->tenant(),
                $names,
                $this->configString($config, 'sort_field'),
                $this->configString($config, 'sort_direction') ?? 'desc',
                $this->filters($config),
                is_numeric($limit) ? max(1, min(FilamentDashboardsPlugin::get()->getBuilderLimit('table'), (int) $limit)) : 10,
                $this->configString($config, 'dates_field'),
                $this->dashboardDates($config),
            );
        } catch (InvalidArgumentException) {
            return TableResult::needingAttention($columns);
        }

        return new TableResult($columns, array_map(function (array $record) use ($shown): TableRow {
            $values = [];
            $display = [];

            foreach ($shown as $field) {
                $value = $record[$field->alias()] ?? null;
                $values[$field->alias()] = is_scalar($value) ? $value : null;
                $display[$field->alias()] = match (true) {
                    is_int($value), is_float($value) => $field->format($value) ?? $this->formatNumber($value),
                    is_scalar($value) => (string) $value,
                    default => null,
                };
            }

            return new TableRow($values, $display);
        }, $records));
    }
}

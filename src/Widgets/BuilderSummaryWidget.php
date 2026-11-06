<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets;

use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use InvalidArgumentException;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateSummary;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Widgets\Concerns\InteractsWithBuilderWidget;

class BuilderSummaryWidget extends TableWidget
{
    use InteractsWithBuilderWidget;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $config = $this->widgetConfig();
        $source = $this->source($config['source'] ?? null);
        $metrics = $this->metrics($config);
        $groupField = $source === null ? null : $this->field($source, $this->configString($config, 'group_field'));

        $columns = [
            TextColumn::make('group')
                ->label($groupField->label ?? '')
                ->translateLabel(false)
                ->weight(fn (array $record): ?FontWeight => $record['total'] ? FontWeight::Bold : null),
        ];

        foreach ($metrics as $index => $metric) {
            $field = $source === null ? null : $this->field($source, $metric['field']);
            $columns[] = TextColumn::make("value_{$index}")
                ->label($metric['label'] ?? implode(' · ', array_filter([$metric['metric']->getLabel(), $field?->label])))
                ->translateLabel(false)
                ->alignment(Alignment::End)
                ->weight(fn (array $record): ?FontWeight => $record['total'] ? FontWeight::Bold : null)
                ->formatStateUsing(fn (int|float $state): string => $field?->format($state) ?? $this->formatNumber($state));

            if ($metric['percent']) {
                $columns[] = TextColumn::make("percent_{$index}")
                    ->label(__('filament-dashboards::dashboards.builder.summary.percent'))
                    ->translateLabel(false)
                    ->alignment(Alignment::End)
                    ->weight(fn (array $record): ?FontWeight => $record['total'] ? FontWeight::Bold : null)
                    ->formatStateUsing(fn (int|float $state): string => number_format($state, 2).'%');
            }
        }

        return $table
            ->heading($this->builderWidget()?->name)
            ->emptyStateDescription(fn (): ?string => $this->needsAttention ? $this->needsAttentionText() : null)
            ->records(fn (): array => $source === null || $metrics === [] ? [] : $this->rows($config, $source, $metrics))
            ->columns($columns)
            ->paginated(false)
            ->poll($this->getPollingInterval());
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array{metric: Metric, field: ?string, label: ?string, percent: bool}>
     */
    protected function metrics(array $config): array
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
     * @param  non-empty-list<array{metric: Metric, field: ?string, label: ?string, percent: bool}>  $metrics
     * @return list<array<string, mixed>>
     */
    protected function rows(array $config, DataSource $source, array $metrics): array
    {
        $limit = $config['limit'] ?? 20;

        try {
            $summary = app(CalculateSummary::class)->handle(
                $source,
                $this->viewer(),
                $this->tenant(),
                $this->configString($config, 'group_field') ?? '',
                array_map(fn (array $metric): array => ['metric' => $metric['metric'], 'field' => $metric['field']], $metrics),
                $this->filters($config),
                is_numeric($limit) ? max(1, min(100, (int) $limit)) : 20,
                $this->configString($config, 'dates_field'),
                $this->dashboardDates($config),
            );
        } catch (InvalidArgumentException) {
            $this->needsAttention = true;

            return [];
        }

        $rows = array_map(fn (array $line): array => $this->record($line['group'], $line['values'], false, $metrics, $summary['totals']), $summary['rows']);

        if ((bool) ($config['total_row'] ?? false)) {
            $rows[] = $this->record(__('filament-dashboards::dashboards.builder.summary.total'), $summary['totals'], true, $metrics, $summary['totals']);
        }

        return $rows;
    }

    /**
     * @param  list<int|float>  $values
     * @param  non-empty-list<array{metric: Metric, field: ?string, label: ?string, percent: bool}>  $metrics
     * @param  list<int|float>  $totals
     * @return array<string, mixed>
     */
    protected function record(string $group, array $values, bool $total, array $metrics, array $totals): array
    {
        $record = ['group' => $group, 'total' => $total];

        foreach ($values as $index => $value) {
            $record["value_{$index}"] = $value;

            if ($metrics[$index]['percent']) {
                $record["percent_{$index}"] = $totals[$index] == 0 ? 0 : $value / $totals[$index] * 100;
            }
        }

        return $record;
    }
}

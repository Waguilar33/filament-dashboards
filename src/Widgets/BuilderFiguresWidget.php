<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets;

use Closure;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateFigureTable;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateMetric;
use Waguilar\FilamentDashboards\Builder\Enums\Arithmetic;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Widgets\Concerns\ExportsData;
use Waguilar\FilamentDashboards\Widgets\Concerns\InteractsWithBuilderWidget;

class BuilderFiguresWidget extends TableWidget
{
    use ExportsData;
    use InteractsWithBuilderWidget;

    public const NUMBER = 'number';

    public const BASE_ROW = 'base';

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $config = $this->widgetConfig();
        $definitions = $this->columnDefinitions($config);
        $formats = $this->formats($definitions);
        $figures = app(CalculateFigureTable::class)->handle(
            $definitions,
            $this->rowDefinitions($config),
        );

        $columns = [TextColumn::make('label')->label('')->translateLabel(false)->weight(FontWeight::Medium)];

        foreach ($definitions as $index => $definition) {
            $share = $figures['shares'][$definition['key']];
            $format = $formats[$definition['key']];

            $columns[] = TextColumn::make("value_{$index}")
                ->label($share === null ? $definition['label'] : new HtmlString('<span class="fi-dashboards-figures-share">'.e(FilamentDashboardsPlugin::get()->formatNumber($share, 1).'%').'</span><br>'.e($definition['label'])))
                ->translateLabel(false)
                ->alignment(Alignment::End)
                ->formatStateUsing(fn (int|float $state): string => $format($state));
        }

        return $table
            ->heading($this->builderWidget()?->name)
            ->headerActions([$this->exportAction()])
            ->description(fn (): ?string => $this->needsAttention ? $this->needsAttentionText() : $this->ownRangeLabel(array_values(array_filter(is_array($config['columns'] ?? null) ? $config['columns'] : [], is_array(...)))))
            ->records(fn (): array => array_map(fn (array $row): array => [
                'label' => $row['label'],
                ...array_combine(
                    array_map(fn (int $index): string => "value_{$index}", array_keys($definitions)),
                    array_values($row['values']),
                ),
            ], $figures['rows']))
            ->columns($columns)
            ->paginated(false)
            ->poll($this->getPollingInterval());
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array{key: string, label: string, figure: int|float|null, left: array{column: ?string, number: int|float|null}, operator: ?Arithmetic, right: array{column: ?string, number: int|float|null}, share_of: ?string, blank_rows: list<string>, format_field: ?Closure(int|float): string}>
     */
    protected function columnDefinitions(array $config): array
    {
        $definitions = [];
        $columns = $config['columns'] ?? [];

        foreach (is_array($columns) ? array_values($columns) : [] as $index => $column) {
            if (! is_array($column)) {
                continue;
            }

            $calculation = ($column['kind'] ?? null) === 'calculation';
            $operator = $calculation ? Arithmetic::tryFrom($this->configString($column, 'operator') ?? '') : null;
            [$figure, $title, $format] = $calculation ? [null, null, null] : $this->figure($column);
            $blankRows = $column['blank_rows'] ?? [];

            $definitions[] = [
                'key' => $this->configString($column, 'key') ?? (string) $index,
                'label' => $this->configString($column, 'label') ?? $title ?? '',
                'figure' => $figure,
                'left' => $this->operand($column, 'left'),
                'operator' => $calculation ? ($operator ?? Arithmetic::Add) : null,
                'right' => $this->operand($column, 'right'),
                'share_of' => $this->configString($column, 'share_of'),
                'blank_rows' => array_values(array_filter(is_array($blankRows) ? $blankRows : [], is_string(...))),
                'format_field' => $format,
            ];
        }

        return $definitions;
    }

    /**
     * @param  array<mixed>  $column
     * @return array{0: int|float|null, 1: ?string, 2: ?Closure(int|float): string}
     */
    protected function figure(array $column): array
    {
        $source = $this->source($column['source'] ?? null);
        $metric = Metric::tryFrom($this->configString($column, 'metric') ?? Metric::Count->value) ?? Metric::Count;

        if ($source === null) {
            return [null, null, null];
        }

        $field = $metric->requiresNumberField() ? $this->field($source, $this->configString($column, 'field')) : null;
        $title = implode(' · ', array_filter([$metric->getLabel(), $field?->label, $source->label()]));

        try {
            $value = app(CalculateMetric::class)->handle($source, $this->viewer(), $this->tenant(), $metric, $this->configString($column, 'field'), $this->filters($column), $this->configString($column, 'dates_field'), $this->dashboardDates($column));
        } catch (InvalidArgumentException) {
            $this->needsAttention = true;

            return [null, $title, null];
        }

        return [$value, $title, $field === null ? null : fn (int|float $value): string => $field->format($value) ?? $this->formatNumber($value)];
    }

    /**
     * @param  array<mixed>  $column
     * @return array{column: ?string, number: int|float|null}
     */
    protected function operand(array $column, string $side): array
    {
        $choice = $this->configString($column, $side);
        $number = $column["{$side}_number"] ?? null;

        return $choice === self::NUMBER
            ? ['column' => null, 'number' => is_numeric($number) ? $number + 0 : null]
            : ['column' => $choice, 'number' => null];
    }

    /**
     * @param  list<array{key: string, left: array{column: ?string, number: int|float|null}, right: array{column: ?string, number: int|float|null}, format_field: ?Closure(int|float): string}>  $definitions
     * @return array<string, Closure(int|float): string>
     */
    protected function formats(array $definitions): array
    {
        $formats = [];

        foreach ($definitions as $definition) {
            $formats[$definition['key']] = $definition['format_field']
                ?? $formats[$definition['left']['column'] ?? ''] ?? $formats[$definition['right']['column'] ?? '']
                ?? fn (int|float $value): string => $this->formatNumber($value);
        }

        return $formats;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array{key: string, label: string, operator: ?Arithmetic, number: int|float|null}>
     */
    protected function rowDefinitions(array $config): array
    {
        $rows = [['key' => self::BASE_ROW, 'label' => $this->configString($config, 'base_label') ?? '', 'operator' => null, 'number' => null]];
        $extra = $config['rows'] ?? [];

        foreach (is_array($extra) ? array_values($extra) : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $number = $row['number'] ?? null;

            $rows[] = [
                'key' => $this->configString($row, 'key') ?? "row_{$index}",
                'label' => $this->configString($row, 'label') ?? '',
                'operator' => Arithmetic::tryFrom($this->configString($row, 'operator') ?? ''),
                'number' => is_numeric($number) ? $number + 0 : null,
            ];
        }

        return $rows;
    }

    protected function exportSourceKeys(): array
    {
        return $this->builderWidget()?->sourceKeys() ?? array_values(array_unique(array_filter(array_map(
            fn (mixed $column): ?string => is_array($column) ? $this->configString($column, 'source') : null,
            is_array($this->widgetConfig()['columns'] ?? null) ? $this->widgetConfig()['columns'] : [],
        ))));
    }
}

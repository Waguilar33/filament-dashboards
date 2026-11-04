<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets;

use Filament\Widgets\ChartWidget;
use InvalidArgumentException;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateBreakdown;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateScatter;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateTrend;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Builder\Enums\TimeGrouping;
use Waguilar\FilamentDashboards\Widgets\Concerns\InteractsWithBuilderWidget;

class BuilderChartWidget extends ChartWidget
{
    use InteractsWithBuilderWidget;

    public const PALETTE = ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16'];

    protected static bool $isDiscovered = false;

    public function getHeading(): ?string
    {
        return $this->builderWidget()?->name;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $config = $this->widgetConfig();
        $source = $this->source($config['source'] ?? null);

        if ($source === null) {
            return ['datasets' => [], 'labels' => []];
        }

        $metric = Metric::tryFrom($this->configString($config, 'metric') ?? Metric::Count->value) ?? Metric::Count;
        $label = $this->configString($config, 'label') ?? $source->label();

        try {
            return match ($this->widgetType()) {
                'line', 'bar' => $this->trend($config, $source, $metric, $label),
                'scatter' => $this->scatter($config, $source, $label),
                default => $this->breakdown($config, $source, $metric, $label),
            };
        } catch (InvalidArgumentException) {
            return ['datasets' => [], 'labels' => []];
        }
    }

    protected function getType(): string
    {
        return $this->widgetType();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getOptions(): ?array
    {
        return match ($this->widgetType()) {
            'pie', 'doughnut', 'polarArea' => ['scales' => ['x' => ['display' => false], 'y' => ['display' => false]]],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function trend(array $config, DataSource $source, Metric $metric, string $label): array
    {
        [$from, $grouping] = match ($this->configString($config, 'period') ?? '12m') {
            '7d' => [now()->subDays(6), TimeGrouping::Day],
            '30d' => [now()->subDays(29), TimeGrouping::Day],
            '90d' => [now()->subDays(89), TimeGrouping::Week],
            'ytd' => [now()->startOfYear(), TimeGrouping::Month],
            default => [now()->subMonths(11), TimeGrouping::Month],
        };

        $grouping = TimeGrouping::tryFrom($this->configString($config, 'grouping') ?? '') ?? $grouping;

        $trend = app(CalculateTrend::class)->handle(
            $source,
            $this->viewer(),
            $this->tenant(),
            $this->configString($config, 'date_field') ?? '',
            $grouping,
            $from,
            now(),
            $metric,
            $this->configString($config, 'field'),
            $this->filters($config),
        );

        return [
            'datasets' => [['label' => $label, 'data' => $trend['values']]],
            'labels' => $trend['labels'],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function breakdown(array $config, DataSource $source, Metric $metric, string $label): array
    {
        $limit = $config['limit'] ?? 10;

        $breakdown = app(CalculateBreakdown::class)->handle(
            $source,
            $this->viewer(),
            $this->tenant(),
            $this->configString($config, 'group_field') ?? '',
            $metric,
            $this->configString($config, 'field'),
            $this->filters($config),
            is_numeric($limit) ? max(1, min(20, (int) $limit)) : 10,
        );

        return [
            'datasets' => [[
                'label' => $label,
                'data' => $breakdown['values'],
                'backgroundColor' => array_slice(array_merge(self::PALETTE, self::PALETTE), 0, count($breakdown['values'])),
                'borderWidth' => 0,
            ]],
            'labels' => $breakdown['labels'],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function scatter(array $config, DataSource $source, string $label): array
    {
        return [
            'datasets' => [[
                'label' => $label,
                'data' => app(CalculateScatter::class)->handle(
                    $source,
                    $this->viewer(),
                    $this->tenant(),
                    $this->configString($config, 'x_field') ?? '',
                    $this->configString($config, 'y_field') ?? '',
                    $this->filters($config),
                ),
            ]],
        ];
    }
}

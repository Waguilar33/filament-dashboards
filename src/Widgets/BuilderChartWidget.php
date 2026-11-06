<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use InvalidArgumentException;
use Waguilar\FilamentDashboards\Actions\ResolveDateRange;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateBreakdown;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateScatter;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateTrend;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Builder\Enums\TimeGrouping;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
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
            $this->needsAttention = true;

            return ['datasets' => [], 'labels' => []];
        }
    }

    public function getDescription(): ?string
    {
        $this->getCachedData();

        return $this->needsAttention ? $this->needsAttentionText() : null;
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
        $dates = app(ResolveDateRange::class)->handle($this->pageFilters);
        $today = CarbonImmutable::now(FilamentDashboardsPlugin::get()->getTimezone());

        $period = $this->configString($config, 'period') ?? '12m';
        $registered = in_array($period, ['dashboard', '7d', '30d', '90d', '12m', 'ytd'], true) ? null : FilamentDashboardsPlugin::get()->getDatePeriod($period)?->range($today);
        $span = $registered ?? $dates;
        $spanDays = (int) $span->start->diffInDays($span->end) + 1;
        $byLength = match (true) {
            $spanDays <= 31 => TimeGrouping::Day,
            $spanDays <= 180 => TimeGrouping::Week,
            default => TimeGrouping::Month,
        };

        [$from, $to, $grouping] = $registered !== null ? [$registered->start, $registered->end, $byLength] : match ($period) {
            'dashboard' => [$dates->start, $dates->end, $byLength],
            '7d' => [$today->subDays(6)->startOfDay(), $today->endOfDay(), TimeGrouping::Day],
            '30d' => [$today->subDays(29)->startOfDay(), $today->endOfDay(), TimeGrouping::Day],
            '90d' => [$today->subDays(89)->startOfDay(), $today->endOfDay(), TimeGrouping::Week],
            'ytd' => [$today->startOfYear(), $today->endOfDay(), TimeGrouping::Month],
            default => [$today->startOfMonth()->subMonths(11), $today->endOfDay(), TimeGrouping::Month],
        };

        $grouping = TimeGrouping::tryFrom($this->configString($config, 'grouping') ?? '') ?? $grouping;

        $trend = app(CalculateTrend::class)->handle(
            $source,
            $this->viewer(),
            $this->tenant(),
            $this->configString($config, 'date_field') ?? '',
            $grouping,
            $from,
            $to,
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
            $this->configString($config, 'dates_field'),
            $this->dashboardDates($config),
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
                    500,
                    $this->configString($config, 'dates_field'),
                    $this->dashboardDates($config),
                ),
            ]],
        ];
    }
}

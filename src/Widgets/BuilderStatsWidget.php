<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use InvalidArgumentException;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateMetric;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Widgets\Concerns\InteractsWithBuilderWidget;

class BuilderStatsWidget extends StatsOverviewWidget
{
    use InteractsWithBuilderWidget;

    protected static bool $isDiscovered = false;

    protected function getHeading(): ?string
    {
        return $this->builderWidget()?->name;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $stats = [];
        $cards = $this->widgetConfig()['cards'] ?? [];

        foreach (is_array($cards) ? $cards : [] as $card) {
            if (! is_array($card)) {
                continue;
            }

            $source = $this->source($card['source'] ?? null);
            $metric = Metric::tryFrom($this->configString($card, 'metric') ?? Metric::Count->value) ?? Metric::Count;

            if ($source === null) {
                continue;
            }

            try {
                $value = app(CalculateMetric::class)->handle($source, $this->viewer(), $this->tenant(), $metric, $this->configString($card, 'field'), $this->filters($card), $this->configString($card, 'dates_field'), $this->dashboardDates($card));
            } catch (InvalidArgumentException) {
                continue;
            }

            $field = $metric->requiresNumberField() ? $this->field($source, $this->configString($card, 'field')) : null;
            $title = implode(' · ', array_filter([$metric->getLabel(), $field?->label, $source->label()]));

            $stats[] = Stat::make($this->configString($card, 'label') ?? $title, $field?->format($value) ?? $this->formatNumber($value));
        }

        return $stats;
    }
}

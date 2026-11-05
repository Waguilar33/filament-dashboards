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

    /**
     * @return int|array<string, ?int>|null
     */
    protected function getColumns(): int|array|null
    {
        return $this->hasCardWidths() ? ['@xl' => 12, '!@lg' => 12] : parent::getColumns();
    }

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
        $hasCardWidths = $this->hasCardWidths();

        foreach ($this->cards() as $card) {
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

            $stat = Stat::make($this->configString($card, 'label') ?? $title, $field?->format($value) ?? $this->formatNumber($value));

            if ($hasCardWidths) {
                $width = $this->cardWidth($card) ?? 12;
                $stat->columnSpan(['@xl' => $width, '!@lg' => $width]);
            }

            $stats[] = $stat;
        }

        return $stats;
    }

    /**
     * @return list<array<mixed>>
     */
    protected function cards(): array
    {
        $cards = $this->widgetConfig()['cards'] ?? [];

        return array_values(array_filter(is_array($cards) ? $cards : [], is_array(...)));
    }

    protected function hasCardWidths(): bool
    {
        foreach ($this->cards() as $card) {
            if ($this->cardWidth($card) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $card
     */
    protected function cardWidth(array $card): ?int
    {
        $width = filter_var($card['width'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);

        return is_int($width) ? $width : null;
    }
}

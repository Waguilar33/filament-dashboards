<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateMetric;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Widgets\Concerns\InteractsWithBuilderWidget;
use Waguilar\FilamentDashboards\Widgets\Stats\DashboardStat;

class BuilderStatsWidget extends StatsOverviewWidget
{
    use InteractsWithBuilderWidget;

    protected static bool $isDiscovered = false;

    public const COLUMNS = 12;

    /**
     * @var array<array-key, int>
     */
    #[Reactive]
    public array $cardWidths = [];

    #[Locked]
    public bool $resizableCards = false;

    /**
     * @return array<string, ?int>
     */
    protected function getColumns(): array
    {
        return ['@sm' => self::COLUMNS, '!@md' => self::COLUMNS];
    }

    /**
     * @return array<DashboardStat>
     */
    protected function getStats(): array
    {
        $stats = [];
        $cards = $this->widgetConfig()['cards'] ?? [];

        foreach (is_array($cards) ? array_values($cards) : [] as $index => $card) {
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

            $stats[] = DashboardStat::make($this->configString($card, 'label') ?? $title, $field?->format($value) ?? $this->formatNumber($value))
                ->translateLabel(false)
                ->cardKey($this->configString($card, 'key') ?? (string) $index)
                ->resizable($this->resizableCards);
        }

        $defaultSpan = intdiv(self::COLUMNS, count($stats) >= 3 && count($stats) % 3 === 1 ? 4 : 3);

        foreach ($stats as $stat) {
            $span = $this->cardWidths[$stat->getCardKey()] ?? null;
            $stat->columnSpan($span === null
                ? ['@sm' => self::COLUMNS, '!@md' => self::COLUMNS, '@xl' => $defaultSpan, '!@lg' => $defaultSpan]
                : ['@sm' => $span, '!@md' => $span]);
        }

        return $stats;
    }
}

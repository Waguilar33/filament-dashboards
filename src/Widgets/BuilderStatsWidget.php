<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets;

use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateMetric;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\Widgets\Concerns\DrillsDownToRecords;
use Waguilar\FilamentDashboards\Widgets\Concerns\InteractsWithBuilderWidget;
use Waguilar\FilamentDashboards\Widgets\Stats\DashboardStat;

class BuilderStatsWidget extends StatsOverviewWidget implements HasActions
{
    use DrillsDownToRecords;
    use InteractsWithActions;
    use InteractsWithBuilderWidget;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament-dashboards::stats-widget';

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

    public function getSectionContentComponent(): Component
    {
        $section = parent::getSectionContentComponent();

        if (! $section instanceof Section || ! (bool) ($this->widgetConfig()['show_title'] ?? false)) {
            return $section;
        }

        return $section
            ->heading($this->builderWidget()->name ?? $this->previewName)
            ->contained();
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
                $stats[] = DashboardStat::make($this->configString($card, 'label') ?? $source->label(), '—')
                    ->translateLabel(false)
                    ->description($this->needsAttentionText())
                    ->color('warning')
                    ->cardKey($this->configString($card, 'key') ?? (string) $index)
                    ->resizable($this->resizableCards);

                continue;
            }

            $field = $metric->requiresNumberField() ? $this->field($source, $this->configString($card, 'field')) : null;
            $title = implode(' · ', array_filter([$metric->getLabel(), $field?->label, $source->label()]));
            $format = fn (int|float $number): string => $field?->format($number) ?? $this->formatNumber($number);

            $stat = DashboardStat::make($this->configString($card, 'label') ?? $title, $format($value))
                ->translateLabel(false)
                ->cardKey($this->configString($card, 'key') ?? (string) $index)
                ->resizable($this->resizableCards)
                ->drillsDown(! $this->resizableCards && $this->drillDownFields($source) !== []);

            $this->describe($stat, $card, $value, $format, fn (DateRange $range): int|float => app(CalculateMetric::class)->handle($source, $this->viewer(), $this->tenant(), $metric, $this->configString($card, 'field'), $this->filters($card), $this->configString($card, 'dates_field'), $range));

            $stats[] = $stat;
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

    /**
     * @param  array<mixed>  $card
     * @param  Closure(int|float): string  $format
     * @param  Closure(DateRange): (int|float)  $calculate
     */
    protected function describe(DashboardStat $stat, array $card, int|float $value, Closure $format, Closure $calculate): void
    {
        $period = $this->configString($card, 'period') ?? 'dashboard';
        $range = $this->dashboardDates($card);
        $goal = is_numeric($card['goal'] ?? null) ? $card['goal'] + 0 : null;
        $parts = [];
        $color = null;
        $icon = null;

        if ($range !== null && $period !== 'dashboard') {
            $parts[] = __("filament-dashboards::dashboards.builder.card_periods.{$period}");
        }

        if ($goal !== null && $goal > 0 && ($range === null || $period !== 'dashboard')) {
            $reached = $value / $goal * 100;
            $parts[] = __('filament-dashboards::builder.goal_progress', ['percent' => number_format($reached), 'goal' => $format($goal)]);
            $color = $reached >= 100 ? 'success' : null;
        }

        if ($range !== null && (bool) ($card['compare'] ?? false)) {
            $now = CarbonImmutable::now($range->end->getTimezone());
            $current = $range->upTo($now);
            $previous = $calculate($current->previous($period));
            $currentValue = $current->end->equalTo($range->end) ? $value : $calculate($current);

            if ($previous == 0 && $currentValue != 0) {
                $parts[] = __('filament-dashboards::builder.compare_new');
            } elseif ($previous != 0) {
                $change = ($currentValue - $previous) / abs($previous) * 100;
                $parts[] = __('filament-dashboards::builder.compare_change', ['change' => ($change > 0 ? '+' : '').number_format($change, 1).'%']);
                $color = $change >= 0 ? 'success' : 'danger';
                $icon = $change >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown;
            }
        }

        if ($parts !== []) {
            $stat->description(implode(' · ', $parts))->descriptionIcon($icon)->color($color);
        }
    }

    protected function drillDownTarget(array $arguments): ?array
    {
        $cards = $this->widgetConfig()['cards'] ?? [];

        foreach (is_array($cards) ? array_values($cards) : [] as $index => $card) {
            if (is_array($card) && ($this->configString($card, 'key') ?? (string) $index) === ($arguments['card'] ?? null)) {
                return ['part' => $card, 'rule' => null, 'title' => $this->configString($card, 'label') ?? $this->source($card['source'] ?? null)?->label() ?? ''];
            }
        }

        return null;
    }
}

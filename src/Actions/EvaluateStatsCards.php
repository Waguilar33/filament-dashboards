<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateMetric;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Builder\Field;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\FilamentDashboardsIcon;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Widgets\Stats\StatCardResult;

class EvaluateStatsCards
{
    public function __construct(
        protected CalculateMetric $calculateMetric,
        protected BuildFilters $buildFilters,
        protected ResolveCardRange $resolveCardRange,
    ) {}

    /**
     * @param  array<mixed>  $cards
     * @return list<StatCardResult>
     */
    public function handle(array $cards, Model $user, ?Model $tenant, DateRange $dashboardRange): array
    {
        $sources = FilamentDashboardsPlugin::get()->getDataSourcesFor($user, $tenant);
        $results = [];

        foreach (array_values($cards) as $index => $card) {
            if (! is_array($card)) {
                continue;
            }

            $source = $sources[self::text($card, 'source') ?? ''] ?? null;

            if ($source === null) {
                continue;
            }

            $metric = Metric::tryFrom(self::text($card, 'metric') ?? Metric::Count->value) ?? Metric::Count;
            $key = self::text($card, 'key') ?? (string) $index;
            $range = $this->range($card, $dashboardRange);
            $calculate = fn (?DateRange $within): int|float => $this->calculateMetric->handle($source, $user, $tenant, $metric, self::text($card, 'field'), $this->buildFilters->handle($card['filters'] ?? []), self::text($card, 'dates_field'), $within);

            try {
                $value = $calculate($range);
            } catch (InvalidArgumentException) {
                $results[] = new StatCardResult($key, self::text($card, 'label') ?? $source->label(), $source, null, '—', __('filament-dashboards::builder.needs_attention'), 'warning', needsAttention: true);

                continue;
            }

            $field = $metric->requiresNumberField() ? self::field($source, self::text($card, 'field')) : null;
            $format = fn (int|float $number): string => $field?->format($number) ?? self::number($number);

            $results[] = new StatCardResult(
                $key,
                self::text($card, 'label') ?? implode(' · ', array_filter([$metric->getLabel(), $field?->label, $source->label()])),
                $source,
                $value,
                $format($value),
                ...$this->describe($card, $range, $value, $format, $calculate),
            );
        }

        return $results;
    }

    /**
     * @param  array<mixed>  $card
     */
    public function range(array $card, DateRange $dashboardRange): ?DateRange
    {
        if (self::text($card, 'dates_field') === null) {
            return null;
        }

        return $this->resolveCardRange->handle(self::text($card, 'period') ?? 'dashboard') ?? $dashboardRange;
    }

    /**
     * @param  array<mixed>  $card
     * @param  Closure(int|float): string  $format
     * @param  Closure(?DateRange): (int|float)  $calculate
     * @return array{description: ?string, color: ?string, icon: string|BackedEnum|null}
     */
    protected function describe(array $card, ?DateRange $range, int|float $value, Closure $format, Closure $calculate): array
    {
        $period = self::text($card, 'period') ?? 'dashboard';
        $goal = is_numeric($card['goal'] ?? null) ? $card['goal'] + 0 : null;
        $parts = [];
        $color = null;
        $icon = null;

        if ($range !== null && $period !== 'dashboard') {
            $parts[] = __("filament-dashboards::dashboards.builder.card_periods.{$period}");
        }

        if ($goal !== null && $goal > 0 && ($range === null || $period !== 'dashboard')) {
            $reached = $value / $goal * 100;
            $parts[] = __('filament-dashboards::builder.goal_progress', ['percent' => FilamentDashboardsPlugin::get()->formatNumber($reached, 0), 'goal' => $format($goal)]);
            $color = $reached >= 100 ? 'success' : null;
        }

        if ($range !== null && (bool) ($card['compare'] ?? false)) {
            $current = $range->upTo(CarbonImmutable::now($range->end->getTimezone()));
            $previous = $calculate(FilamentDashboardsPlugin::get()->getDatePeriod($period)?->previous($current) ?? $current->previous());
            $currentValue = $current->end->equalTo($range->end) ? $value : $calculate($current);

            if ($previous == 0 && $currentValue != 0) {
                $parts[] = __('filament-dashboards::builder.compare_new');
            } elseif ($previous != 0) {
                $change = ($currentValue - $previous) / abs($previous) * 100;
                $parts[] = __('filament-dashboards::builder.compare_change', ['change' => ($change > 0 ? '+' : '').FilamentDashboardsPlugin::get()->formatNumber($change, 1).'%']);
                $color = $change >= 0 ? 'success' : 'danger';
                $resolved = FilamentDashboardsIcon::get($change >= 0 ? FilamentDashboardsIcon::INCREASE : FilamentDashboardsIcon::DECREASE);
                $icon = $resolved instanceof Htmlable ? null : $resolved;
            }
        }

        return ['description' => $parts === [] ? null : implode(' · ', $parts), 'color' => $color, 'icon' => $icon];
    }

    /**
     * @param  array<mixed>  $config
     */
    protected static function text(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        return is_string($value) && filled($value) ? $value : null;
    }

    protected static function field(DataSource $source, ?string $name): ?Field
    {
        foreach ($source->fields() as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    protected static function number(int|float $value): string
    {
        return FilamentDashboardsPlugin::get()->formatNumber($value);
    }
}

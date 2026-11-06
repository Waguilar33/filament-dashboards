<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

class ResolveDateRange
{
    public const CUSTOM = 'custom';

    public const MAX_YEARS = 5;

    /**
     * @param  array<string, mixed>|null  $dates
     */
    public function handle(?array $dates): DateRange
    {
        $plugin = FilamentDashboardsPlugin::get();
        $timezone = $plugin->getTimezone();
        $preset = $dates['preset'] ?? null;
        $from = $dates['startDate'] ?? null;
        $until = $dates['endDate'] ?? null;

        $start = $preset === self::CUSTOM ? $this->parse($from, $timezone) : null;
        $end = $preset === self::CUSTOM ? $this->parse($until, $timezone) : null;

        if ($start !== null && $end !== null) {
            [$start, $end] = $start->lessThanOrEqualTo($end) ? [$start, $end] : [$end, $start];

            return new DateRange(
                $start->max($end->subYears(self::MAX_YEARS))->startOfDay(),
                $end->endOfDay(),
            );
        }

        $days = is_numeric($preset) && in_array((int) $preset, $plugin->getDatePresets(), true)
            ? (int) $preset
            : $plugin->getDefaultDatePreset();
        $today = CarbonImmutable::now($timezone);

        return new DateRange($today->subDays($days - 1)->startOfDay(), $today->endOfDay());
    }

    protected function parse(mixed $date, string $timezone): ?CarbonImmutable
    {
        if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        return $parsed instanceof CarbonImmutable && $parsed->format('Y-m-d') === $date ? $parsed : null;
    }
}

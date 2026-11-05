<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

class ResolveDateRange
{
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

        if (blank($preset) && is_string($from) && filled($from) && is_string($until) && filled($until)) {
            $start = CarbonImmutable::parse($from, $timezone)->startOfDay();
            $end = CarbonImmutable::parse($until, $timezone)->endOfDay();

            return $start->lessThanOrEqualTo($end)
                ? new DateRange($start, $end)
                : new DateRange($end->startOfDay(), $start->endOfDay());
        }

        $days = is_numeric($preset) && in_array((int) $preset, $plugin->getDatePresets(), true)
            ? (int) $preset
            : $plugin->getDefaultDatePreset();
        $today = CarbonImmutable::now($timezone);

        return new DateRange($today->subDays($days - 1)->startOfDay(), $today->endOfDay());
    }
}

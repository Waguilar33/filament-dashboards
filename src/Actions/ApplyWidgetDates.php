<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

class ApplyWidgetDates
{
    public const DASHBOARD = 'dashboard';

    /**
     * @param  array<string, mixed>  $config
     * @param  array{preset: int|string, startDate?: string, endDate?: string}  $dates
     * @return array<string, mixed>
     */
    public function handle(array $config, array $dates): array
    {
        $period = $this->period($dates);
        $config = $this->isDated($config) ? [...$config, ...$period] : $config;

        foreach (['cards', 'columns'] as $key) {
            if (is_array($config[$key] ?? null)) {
                $config[$key] = array_map(fn (mixed $part): mixed => is_array($part) && $this->isDated($part) ? [...$part, ...$period] : $part, $config[$key]);
            }
        }

        return $config;
    }

    /**
     * @param  array{preset: int|string, startDate?: string, endDate?: string}  $dates
     * @return array{period: string, period_start?: string, period_end?: string}
     */
    protected function period(array $dates): array
    {
        $preset = $dates['preset'];

        if ($preset === self::DASHBOARD || (is_string($preset) && $preset !== ResolveDateRange::CUSTOM)) {
            return ['period' => $preset];
        }

        if ($preset === ResolveDateRange::CUSTOM) {
            return ['period' => ResolveWidgetRange::OWN_RANGE, 'period_start' => $dates['startDate'] ?? '', 'period_end' => $dates['endDate'] ?? ''];
        }

        $today = CarbonImmutable::now(FilamentDashboardsPlugin::get()->getTimezone());

        return ['period' => ResolveWidgetRange::OWN_RANGE, 'period_start' => $today->subDays($preset - 1)->toDateString(), 'period_end' => $today->toDateString()];
    }

    /**
     * @param  array<mixed>  $part
     */
    protected function isDated(array $part): bool
    {
        return filled($part['dates_field'] ?? null) || filled($part['date_field'] ?? null);
    }
}

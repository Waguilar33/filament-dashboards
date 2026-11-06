<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

class ResolveWidgetRange
{
    public const OWN_RANGE = 'custom';

    public function __construct(protected ResolveDateRange $resolveDateRange) {}

    /**
     * @param  array<mixed>  $part
     */
    public function handle(array $part): ?DateRange
    {
        $plugin = FilamentDashboardsPlugin::get();
        $period = is_string($part['period'] ?? null) ? $part['period'] : 'dashboard';

        if ($period !== self::OWN_RANGE) {
            return $plugin->getDatePeriod($period)?->range(CarbonImmutable::now($plugin->getTimezone()));
        }

        $start = $part['period_start'] ?? null;
        $end = $part['period_end'] ?? null;

        if (! is_string($start) || ! is_string($end) || blank($start) || blank($end)) {
            throw new InvalidArgumentException('A widget with its own dates needs a start and an end.');
        }

        return $this->resolveDateRange->handle(['preset' => ResolveDateRange::CUSTOM, 'startDate' => $start, 'endDate' => $end]);
    }
}

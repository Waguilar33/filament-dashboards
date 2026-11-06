<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

enum TimeGrouping: string implements HasLabel
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function getLabel(): string
    {
        return trans('filament-dashboards::builder.groupings.'.$this->value);
    }

    public function start(CarbonInterface $date): CarbonImmutable
    {
        $date = CarbonImmutable::instance($date);

        return match ($this) {
            self::Day => $date->startOfDay(),
            self::Week => $date->startOfWeek(FilamentDashboardsPlugin::get()->getWeekStart()),
            self::Month => $date->startOfMonth(),
            self::Year => $date->startOfYear(),
        };
    }

    public function next(CarbonImmutable $start): CarbonImmutable
    {
        return match ($this) {
            self::Day => $start->addDay(),
            self::Week => $start->addWeek(),
            self::Month => $start->addMonth(),
            self::Year => $start->addYear(),
        };
    }

    public function label(CarbonImmutable $start): string
    {
        return FilamentDashboardsPlugin::get()->formatDate($start, $this->value);
    }
}

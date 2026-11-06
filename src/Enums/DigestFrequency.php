<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Enums;

enum DigestFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function getLabel(): string
    {
        return __("filament-dashboards::dashboards.digest.frequencies.{$this->value}");
    }
}

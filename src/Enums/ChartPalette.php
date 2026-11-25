<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Enums;

use Filament\Support\Contracts\HasLabel;

enum ChartPalette: string implements HasLabel
{
    case Varied = 'varied';
    case Single = 'single';

    public function getLabel(): string
    {
        return trans('filament-dashboards::builder.palettes.'.$this->value);
    }
}

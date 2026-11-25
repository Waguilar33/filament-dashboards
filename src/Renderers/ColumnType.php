<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Renderers;

enum ColumnType: string
{
    case Text = 'text';
    case Number = 'number';
    case Percent = 'percent';
    case Date = 'date';
}

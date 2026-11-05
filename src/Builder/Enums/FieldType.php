<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Enums;

use Filament\Support\Contracts\HasLabel;

enum FieldType: string implements HasLabel
{
    case Number = 'number';
    case Text = 'text';
    case Date = 'date';
    case Boolean = 'boolean';
    case Choice = 'choice';

    public function getLabel(): string
    {
        return trans('filament-dashboards::builder.field_types.'.$this->value);
    }
}

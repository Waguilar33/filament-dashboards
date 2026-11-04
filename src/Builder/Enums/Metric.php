<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Enums;

use Filament\Support\Contracts\HasLabel;

enum Metric: string implements HasLabel
{
    case Count = 'count';
    case Sum = 'sum';
    case Average = 'average';
    case Min = 'min';
    case Max = 'max';

    public function getLabel(): string
    {
        return trans('filament-dashboards::builder.metrics.'.$this->value);
    }

    public function requiresNumberField(): bool
    {
        return $this !== self::Count;
    }

    /**
     * @param  list<int|float>  $values
     */
    public function aggregate(array $values): int|float
    {
        return match ($this) {
            self::Count => count($values),
            self::Sum => array_sum($values),
            self::Average => $values === [] ? 0 : array_sum($values) / count($values),
            self::Min => $values === [] ? 0 : min($values),
            self::Max => $values === [] ? 0 : max($values),
        };
    }
}

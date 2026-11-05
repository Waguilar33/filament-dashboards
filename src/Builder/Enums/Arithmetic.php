<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Enums;

use Filament\Support\Contracts\HasLabel;

enum Arithmetic: string implements HasLabel
{
    case Add = 'add';
    case Subtract = 'subtract';
    case Multiply = 'multiply';
    case Divide = 'divide';

    public function getLabel(): string
    {
        return match ($this) {
            self::Add => '+',
            self::Subtract => '−',
            self::Multiply => '×',
            self::Divide => '÷',
        };
    }

    public function apply(int|float|null $left, int|float|null $right): int|float|null
    {
        if ($left === null || $right === null) {
            return null;
        }

        return match ($this) {
            self::Add => $left + $right,
            self::Subtract => $left - $right,
            self::Multiply => $left * $right,
            self::Divide => $right == 0 ? null : $left / $right,
        };
    }
}

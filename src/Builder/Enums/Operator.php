<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Enums;

use Filament\Support\Contracts\HasLabel;

enum Operator: string implements HasLabel
{
    case Is = 'is';
    case IsNot = 'is_not';
    case Contains = 'contains';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case Before = 'before';
    case After = 'after';
    case IsEmpty = 'is_empty';
    case IsNotEmpty = 'is_not_empty';

    /**
     * @return list<self>
     */
    public static function forType(FieldType $type): array
    {
        return match ($type) {
            FieldType::Number => [self::Is, self::IsNot, self::GreaterThan, self::LessThan, self::IsEmpty, self::IsNotEmpty],
            FieldType::Text => [self::Is, self::IsNot, self::Contains, self::IsEmpty, self::IsNotEmpty],
            FieldType::Date => [self::Is, self::IsNot, self::Before, self::After, self::IsEmpty, self::IsNotEmpty],
            FieldType::Boolean => [self::Is, self::IsNot],
            FieldType::Choice => [self::Is, self::IsNot, self::IsEmpty, self::IsNotEmpty],
        };
    }

    public function requiresValue(): bool
    {
        return $this !== self::IsEmpty && $this !== self::IsNotEmpty;
    }

    public function getLabel(): string
    {
        return trans('filament-dashboards::builder.operators.'.$this->value);
    }
}

<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

final readonly class AggregateExpression implements Expression
{
    public function __construct(
        private string $function,
        private ?string $column,
        private string $alias,
    ) {}

    public function getValue(Grammar $grammar): string
    {
        $argument = $this->column === null ? '*' : $grammar->wrap($this->column);

        return "{$this->function}({$argument}) as {$grammar->wrap($this->alias)}";
    }
}

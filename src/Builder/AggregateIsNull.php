<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

final readonly class AggregateIsNull implements Expression
{
    public function __construct(
        private string $function,
        private ?string $column,
    ) {}

    public function getValue(Grammar $grammar): string
    {
        $argument = $this->column === null ? '*' : $grammar->wrap($this->column);

        return "case when {$this->function}({$argument}) is null then 1 else 0 end";
    }
}

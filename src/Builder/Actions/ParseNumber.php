<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

class ParseNumber
{
    public function handle(mixed $value): int|float
    {
        return is_numeric($value) ? $value + 0 : 0;
    }
}

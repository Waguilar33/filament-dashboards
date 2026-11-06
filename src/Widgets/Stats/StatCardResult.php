<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets\Stats;

use BackedEnum;
use Waguilar\FilamentDashboards\Builder\DataSource;

final readonly class StatCardResult
{
    public function __construct(
        public string $key,
        public string $label,
        public ?DataSource $source,
        public int|float|null $value,
        public string $formatted,
        public ?string $description = null,
        public ?string $color = null,
        public string|BackedEnum|null $icon = null,
        public bool $needsAttention = false,
    ) {}
}

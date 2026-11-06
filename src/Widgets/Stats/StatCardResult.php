<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets\Stats;

use Filament\Support\Icons\Heroicon;
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
        public ?Heroicon $icon = null,
        public bool $needsAttention = false,
    ) {}
}

<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Renderers;

final readonly class TableRow
{
    /**
     * @param  array<string, bool|int|float|string|null>  $values
     * @param  array<string, string|null>  $display
     * @param  array<string, mixed>|null  $drillDown
     */
    public function __construct(
        public array $values,
        public array $display,
        public ?array $drillDown = null,
    ) {}

    public function value(string $key): bool|int|float|string|null
    {
        return $this->values[$key] ?? null;
    }

    public function display(string $key): ?string
    {
        return $this->display[$key] ?? null;
    }

    public function drillsDown(): bool
    {
        return $this->drillDown !== null;
    }

    /**
     * @return array{values: array<string, bool|int|float|string|null>, display: array<string, string|null>, drillDown: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'values' => $this->values,
            'display' => $this->display,
            'drillDown' => $this->drillDown,
        ];
    }
}

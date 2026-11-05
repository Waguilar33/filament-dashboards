<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\SystemDashboards;

use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;

abstract class SystemWidget
{
    abstract public function key(): string;

    abstract public function name(): string;

    abstract public function type(): BuilderWidgetType;

    /**
     * @return array<string, mixed>
     */
    abstract public function config(): array;

    public function refreshInterval(): ?int
    {
        return null;
    }
}

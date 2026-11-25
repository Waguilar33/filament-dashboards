<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Renderers;

use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;

abstract class TableRenderer implements BuilderWidgetRenderer
{
    abstract public function widget(BuilderWidgetType $type): string;

    public function skeleton(BuilderWidgetType $type): string
    {
        return 'table';
    }
}

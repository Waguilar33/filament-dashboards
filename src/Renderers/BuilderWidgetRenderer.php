<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Renderers;

use Filament\Widgets\Widget;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;

interface BuilderWidgetRenderer
{
    /**
     * @return class-string<Widget>
     */
    public function widget(BuilderWidgetType $type): string;

    public function skeleton(BuilderWidgetType $type): string;
}

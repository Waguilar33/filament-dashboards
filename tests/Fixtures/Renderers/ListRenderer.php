<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures\Renderers;

use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Renderers\BuilderWidgetRenderer;

final class ListRenderer implements BuilderWidgetRenderer
{
    public function widget(BuilderWidgetType $type): string
    {
        return ListWidget::class;
    }

    public function skeleton(BuilderWidgetType $type): string
    {
        return 'card';
    }
}

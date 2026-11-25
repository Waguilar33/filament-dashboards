<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures\Renderers;

use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Renderers\TableRenderer;

final class ListRenderer extends TableRenderer
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

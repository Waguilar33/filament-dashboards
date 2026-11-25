<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Renderers;

use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Widgets\BuilderSummaryWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderTableWidget;

final class FilamentTableRenderer implements BuilderWidgetRenderer
{
    public function widget(BuilderWidgetType $type): string
    {
        return $type === BuilderWidgetType::Summary ? BuilderSummaryWidget::class : BuilderTableWidget::class;
    }

    public function skeleton(BuilderWidgetType $type): string
    {
        return 'table';
    }
}

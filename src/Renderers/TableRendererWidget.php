<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Renderers;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Waguilar\FilamentDashboards\Renderers\Concerns\HasBuilderHeaderActions;
use Waguilar\FilamentDashboards\Renderers\Concerns\InteractsWithBuilderTable;

abstract class TableRendererWidget extends RendererWidget
{
    use HasBuilderHeaderActions;
    use InteractsWithBuilderTable;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament-dashboards::table-renderer';

    abstract protected function body(TableResult $result): View|Htmlable;
}

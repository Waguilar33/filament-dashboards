<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builders;

use Illuminate\Database\Eloquent\Builder;
use Waguilar\FilamentDashboards\Builders\Concerns\ScopesToViewer;
use Waguilar\FilamentDashboards\Models\BuilderWidget;

/**
 * @extends Builder<BuilderWidget>
 */
class BuilderWidgetBuilder extends Builder
{
    use ScopesToViewer;
}

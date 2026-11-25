<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Renderers;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;

/**
 * @internal Extend TableRendererWidget instead.
 */
abstract class RendererWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
}

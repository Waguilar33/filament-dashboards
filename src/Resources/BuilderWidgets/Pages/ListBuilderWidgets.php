<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Resources\BuilderWidgets\Pages;

use Filament\Resources\Pages\ListRecords;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\BuilderWidgetResource;

class ListBuilderWidgets extends ListRecords
{
    protected static string $resource = BuilderWidgetResource::class;
}

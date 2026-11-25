<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures\Renderers;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Waguilar\FilamentDashboards\Renderers\TableRendererWidget;
use Waguilar\FilamentDashboards\Renderers\TableResult;

final class SchemaWidget extends TableRendererWidget
{
    public function rows(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('groups')->state(fn (): string => implode(', ', array_map(fn ($row): string => (string) $row->display('group'), $this->tableResult()->rows))),
        ]);
    }

    protected function body(TableResult $result): Schema
    {
        return $this->rows;
    }
}

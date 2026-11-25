<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets;

use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Js;
use Waguilar\FilamentDashboards\Renderers\Concerns\InteractsWithBuilderTable;
use Waguilar\FilamentDashboards\Renderers\TableColumn;
use Waguilar\FilamentDashboards\Renderers\TableRow;

class BuilderTableWidget extends TableWidget
{
    use InteractsWithBuilderTable;

    protected const TOTAL = '__total';

    protected const DRILL_DOWN = '__drill_down';

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function tableResultChanged(): void
    {
        $this->table = $this->table($this->makeTable());
    }

    public function table(Table $table): Table
    {
        $result = $this->tableResult();
        $drills = array_filter($result->rows, fn (TableRow $row): bool => $row->drillsDown()) !== [];

        return $table
            ->heading($this->builderHeading())
            ->description($this->builderDescription())
            ->headerActions($this->builderHeaderActions())
            ->emptyStateDescription(fn (): ?string => $this->builderEmptyStateDescription())
            ->records(fn (): array => $this->tableRecords())
            ->columns(array_map(fn (TableColumn $column, int $index): TextColumn => TextColumn::make($column->key)
                ->label($column->label)
                ->translateLabel(false)
                ->when($column->alignment === Alignment::End, fn (TextColumn $text): TextColumn => $text->alignment(Alignment::End))
                ->when($this->boldsTotals() || $result->totals instanceof TableRow, fn (TextColumn $text): TextColumn => $text->weight(fn (array $record): ?FontWeight => $record[self::TOTAL] ? FontWeight::Bold : null))
                ->when($drills && $index === 0, fn (TextColumn $text): TextColumn => $text->extraCellAttributes(fn (array $record): array => $record[self::DRILL_DOWN] === null ? [] : [
                    'wire:click' => 'mountAction(\'drillDown\', '.Js::from($record[self::DRILL_DOWN]).')',
                    'class' => 'fi-dashboards-drill-down',
                ])), $result->columns, array_keys($result->columns)))
            ->paginated(false)
            ->poll($this->builderPollingInterval());
    }

    protected function boldsTotals(): bool
    {
        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function tableRecords(): array
    {
        $result = $this->tableResult();
        $record = fn (TableRow $row, bool $total): array => [...$row->display, self::TOTAL => $total, self::DRILL_DOWN => $row->drillDown];

        return [
            ...array_map(fn (TableRow $row): array => $record($row, false), $result->rows),
            ...($result->totals instanceof TableRow ? [$record($result->totals, true)] : []),
        ];
    }
}

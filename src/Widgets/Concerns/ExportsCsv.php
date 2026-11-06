<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets\Concerns;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsCsv
{
    protected function exportAction(): Action
    {
        return Action::make('export')
            ->label(__('filament-dashboards::dashboards.actions.export'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->size('sm')
            ->action(fn (): StreamedResponse => $this->exportCsv());
    }

    protected function exportCsv(): StreamedResponse
    {
        $columns = $this->getTable()->getColumns();
        $records = $this->getTableRecords();
        $records = $records instanceof Collection ? $records->all() : $records->items();
        $name = Str::slug($this->builderWidget()->name ?? 'widget').'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($columns, $records): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            // Excel only reads UTF-8 accents when the file starts with a byte order mark; a leading = + - @ in text would run as a formula.
            fwrite($output, "\u{FEFF}");
            fputcsv($output, array_map(fn (Column $column): string => $this->plainLabel($column->getLabel()), array_values($columns)), escape: '');

            foreach ($records as $record) {
                fputcsv($output, array_map(function (Column $column) use ($record): string {
                    $value = is_array($record) ? ($record[$column->getName()] ?? null) : data_get($record, $column->getName());

                    if (is_string($value) && ! is_numeric($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
                        return "'".$value;
                    }

                    return is_scalar($value) ? (string) $value : '';
                }, array_values($columns)), escape: '');
            }

            fclose($output);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function plainLabel(string|Htmlable|null $label): string
    {
        $text = $label instanceof Htmlable ? $label->toHtml() : (string) $label;

        return trim(html_entity_decode(strip_tags(str_replace('<br>', ' ', $text))));
    }
}

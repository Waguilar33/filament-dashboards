<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\Column;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Waguilar\FilamentDashboards\Exports\WidgetExporter;
use Waguilar\FilamentDashboards\FilamentDashboardsIcon;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

trait ExportsData
{
    /**
     * @return list<string>
     */
    abstract protected function exportSourceKeys(): array;

    protected function exportAction(): Action|ActionGroup
    {
        $plugin = FilamentDashboardsPlugin::get();
        $exporters = $plugin->getExporters();
        $allowed = fn (): bool => collect($this->exportSourceKeys())->every(fn (string $key): bool => ($source = $this->source($key)) !== null && $source->canExport($this->viewer(), $this->tenant()));
        $actions = array_map(fn (WidgetExporter $exporter): Action => $plugin->modifyAction(Action::make("export_{$exporter->key()}")
            ->label(count($exporters) === 1 ? __('filament-dashboards::dashboards.actions.export') : $exporter->label())
            ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::EXPORT))
            ->color('gray')
            ->size('sm')
            ->visible($allowed)
            ->action(fn (): StreamedResponse => $this->exportWith($exporter)), count($exporters) === 1 ? 'widget.export' : "widget.export.{$exporter->key()}"), $exporters);

        return count($actions) === 1 ? $actions[0] : ActionGroup::make($actions)
            ->label(__('filament-dashboards::dashboards.actions.export'))
            ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::EXPORT))
            ->color('gray')
            ->size('sm')
            ->button();
    }

    protected function exportWith(WidgetExporter $exporter): StreamedResponse
    {
        abort_unless(collect($this->exportSourceKeys())->every(fn (string $key): bool => ($source = $this->source($key)) !== null && $source->canExport($this->viewer(), $this->tenant())), 403);

        $columns = array_values($this->getTable()->getColumns());
        $records = $this->getTableRecords();
        $records = $records instanceof Collection ? $records->all() : $records->items();

        return $exporter->export(
            FilamentDashboardsPlugin::get()->exportFileName($this->builderWidget()->name ?? 'widget'),
            array_map(fn (Column $column): string => $this->plainLabel($column->getLabel()), $columns),
            array_values(array_map(fn (mixed $record): array => array_map(function (Column $column) use ($record): bool|float|int|string|null {
                $value = is_array($record) ? ($record[$column->getName()] ?? null) : data_get($record, $column->getName());

                return is_scalar($value) ? $value : null;
            }, $columns), $records)),
        );
    }

    protected function plainLabel(string|Htmlable|null $label): string
    {
        $text = $label instanceof Htmlable ? $label->toHtml() : (string) $label;

        return trim(html_entity_decode(strip_tags(str_replace('<br>', ' ', $text))));
    }
}

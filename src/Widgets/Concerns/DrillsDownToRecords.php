<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets\Concerns;

use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use InvalidArgumentException;
use Waguilar\FilamentDashboards\Builder\Actions\FetchRows;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\FieldType;
use Waguilar\FilamentDashboards\Builder\Field;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

trait DrillsDownToRecords
{
    /**
     * @param  array<mixed>  $arguments
     * @return array{part: array<mixed>, rule: ?array{field: string, operator: string, value?: mixed}, title: string}|null
     */
    abstract protected function drillDownTarget(array $arguments): ?array;

    public function drillDownAction(): Action
    {
        return FilamentDashboardsPlugin::get()->modifyAction(Action::make('drillDown')
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading(fn (array $arguments): string => $this->drillDownTarget($arguments)['title'] ?? '')
            ->modalDescription(__('filament-dashboards::dashboards.modals.drill_down.description', ['count' => FilamentDashboardsPlugin::get()->getBuilderLimit('drill_down')]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament-dashboards::dashboards.actions.close'))
            ->schema(function (array $arguments): array {
                $target = $this->drillDownTarget($arguments);
                $source = $target === null ? null : $this->source($target['part']['source'] ?? null);

                $fields = $source instanceof DataSource ? $this->drillDownFields($source) : [];

                abort_if($target === null || $fields === [], 403);

                return [
                    RepeatableEntry::make('records')
                        ->hiddenLabel()
                        ->placeholder(__('filament-dashboards::dashboards.modals.drill_down.empty'))
                        ->state(fn (): array => $this->drillDownRows($source, $fields, $target['part'], $target['rule']))
                        ->table(array_map(fn (Field $field): TableColumn => TableColumn::make($field->label)->alignment($field->type === FieldType::Number ? Alignment::End : Alignment::Start), $fields))
                        ->schema(array_map(fn (Field $field): TextEntry => TextEntry::make($field->alias())
                            ->alignment($field->type === FieldType::Number ? Alignment::End : Alignment::Start)
                            ->formatStateUsing(fn (mixed $state): mixed => is_int($state) || is_float($state) ? ($field->format($state) ?? $this->formatNumber($state)) : $state), $fields)),
                ];
            }), 'widget.drillDown');
    }

    public function canDrillDown(mixed $source): bool
    {
        $source = $this->source($source);

        return $source instanceof DataSource && $this->drillDownFields($source) !== [];
    }

    /**
     * @return list<Field>
     */
    protected function drillDownFields(DataSource $source): array
    {
        $names = $source->drillDownFields($this->viewer(), $this->tenant());

        return array_values(array_filter($source->fields(), fn (Field $field): bool => in_array($field->name, $names, true)));
    }

    /**
     * @param  list<Field>  $fields
     * @param  array<mixed>  $part
     * @param  array{field: string, operator: string, value?: mixed}|null  $rule
     * @return list<array<string, mixed>>
     */
    protected function drillDownRows(DataSource $source, array $fields, array $part, ?array $rule): array
    {
        $filters = $this->filters($part);

        if ($rule !== null) {
            $filters = $filters === [] ? [[$rule]] : array_map(fn (array $group): array => [...$group, $rule], $filters);
        }

        $sort = collect($source->fields())->first(fn (Field $field): bool => $field->type === FieldType::Date);

        try {
            return app(FetchRows::class)->handle(
                $source,
                $this->viewer(),
                $this->tenant(),
                array_map(fn (Field $field): string => $field->name, $fields),
                $sort?->name,
                'desc',
                $filters,
                FilamentDashboardsPlugin::get()->getBuilderLimit('drill_down'),
                $this->configString($part, 'dates_field'),
                $this->dashboardDates($part),
            );
        } catch (InvalidArgumentException) {
            return [];
        }
    }
}

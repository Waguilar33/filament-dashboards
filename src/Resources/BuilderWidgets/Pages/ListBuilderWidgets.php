<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Resources\BuilderWidgets\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Arr;
use Waguilar\FilamentDashboards\Filament\Actions\BuildWidgetAction;
use Waguilar\FilamentDashboards\Forms\WidgetPicker;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\BuilderWidgetResource;

class ListBuilderWidgets extends ListRecords
{
    protected static string $resource = BuilderWidgetResource::class;

    public function buildWidgetAction(): Action
    {
        return BuildWidgetAction::make()
            ->visible(BuilderWidgetResource::canCreate())
            ->modalSubmitActionLabel(__('filament-dashboards::dashboards.actions.create'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label(__('filament-dashboards::dashboards.actions.create'))
                ->visible(BuilderWidgetResource::canCreate())
                ->slideOver()
                ->modalWidth(Width::FourExtraLarge)
                ->modalHeading(__('filament-actions::create.single.modal.heading', ['label' => BuilderWidgetResource::getModelLabel()]))
                ->modalSubmitActionLabel(__('filament-dashboards::dashboards.actions.next'))
                ->schema([
                    WidgetPicker::make('type')
                        ->hiddenLabel()
                        ->required()
                        ->sections([BuildWidgetAction::pickerSection()]),
                ])
                ->action(fn (array $data) => $this->replaceMountedAction('buildWidget', ['type' => Arr::string($data, 'type')])),
        ];
    }
}

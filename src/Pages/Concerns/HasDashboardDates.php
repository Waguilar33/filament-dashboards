<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Pages\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Waguilar\FilamentDashboards\Actions\ResolveDateRange;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

trait HasDashboardDates
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        $plugin = FilamentDashboardsPlugin::get();
        $presets = [];

        foreach ($plugin->getDatePresets() as $preset) {
            $presets[$preset] = match (true) {
                is_string($preset) => $plugin->getDatePeriod($preset)?->label() ?? $preset,
                $preset === 1 => __('filament-dashboards::dashboards.dates.today'),
                default => trans_choice('filament-dashboards::dashboards.dates.days', $preset, ['count' => $preset]),
            };
        }

        $presets[ResolveDateRange::CUSTOM] = __('filament-dashboards::dashboards.dates.custom');

        return $schema
            ->components([
                Flex::make([
                    ToggleButtons::make('preset')
                        ->hiddenLabel()
                        ->options($presets)
                        ->default($plugin->getDefaultDatePreset())
                        ->grouped()
                        ->extraAttributes(['class' => 'fi-dashboards-dates'])
                        ->grow(false)
                        ->afterStateUpdated(function (Set $set, mixed $state, mixed $old): void {
                            $range = app(ResolveDateRange::class)->handle(['preset' => $state === ResolveDateRange::CUSTOM ? $old : $state]);
                            $set('startDate', $range->start->toDateString());
                            $set('endDate', $range->end->toDateString());
                        }),
                    FusedGroup::make([
                        DatePicker::make('startDate')
                            ->hiddenLabel()
                            ->live(condition: false)
                            ->prefix(__('filament-dashboards::dashboards.dates.from'))
                            ->extraInputAttributes(['x-on:input' => 'edited = true'])
                            ->afterStateHydrated(fn (DatePicker $component, Get $get) => $this->showPresetRange($component, $get, 'start')),
                        DatePicker::make('endDate')
                            ->hiddenLabel()
                            ->live(condition: false)
                            ->prefix(__('filament-dashboards::dashboards.dates.until'))
                            ->extraInputAttributes(['x-on:input' => 'edited = true'])
                            ->afterStateHydrated(fn (DatePicker $component, Get $get) => $this->showPresetRange($component, $get, 'end'))
                            ->suffixAction(Action::make('applyDates')
                                ->label(__('filament-dashboards::dashboards.dates.apply'))
                                ->button()
                                ->extraAttributes(['x-bind:disabled' => '! edited'])
                                ->alpineClickHandler('edited = false; $wire.applyDashboardDates()')),
                    ])
                        ->columns(['default' => 2])
                        ->extraAttributes(['class' => 'fi-dashboards-dates fi-dashboards-custom-dates', 'x-data' => '{ edited: false }'])
                        ->grow(false),
                ])
                    ->from('sm')
                    ->extraAttributes(['class' => 'fi-dashboards-date-bar'])
                    ->columnSpanFull(),
            ]);
    }

    public function applyDashboardDates(): void
    {
        $this->filters['preset'] = ResolveDateRange::CUSTOM;
    }

    protected function showPresetRange(DatePicker $component, Get $get, string $edge): void
    {
        if ($get('preset') === ResolveDateRange::CUSTOM && filled($component->getState())) {
            return;
        }

        $range = app(ResolveDateRange::class)->handle(['preset' => $get('preset')]);
        $component->state(($edge === 'start' ? $range->start : $range->end)->toDateString());
    }
}

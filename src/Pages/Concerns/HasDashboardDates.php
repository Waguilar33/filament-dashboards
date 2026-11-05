<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Pages\Concerns;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Flex;
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

        foreach ($plugin->getDatePresets() as $days) {
            $presets[$days] = $days === 1
                ? __('filament-dashboards::dashboards.dates.today')
                : trans_choice('filament-dashboards::dashboards.dates.days', $days, ['count' => $days]);
        }

        $range = app(ResolveDateRange::class)->handle(null);

        return $schema
            ->components([
                Flex::make([
                    ToggleButtons::make('preset')
                        ->hiddenLabel()
                        ->options($presets)
                        ->default($plugin->getDefaultDatePreset())
                        ->grouped()
                        ->grow(false)
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            if (blank($state)) {
                                return;
                            }

                            $range = app(ResolveDateRange::class)->handle(['preset' => $state]);
                            $set('startDate', $range->start->toDateString());
                            $set('endDate', $range->end->toDateString());
                        }),
                    DatePicker::make('startDate')
                        ->hiddenLabel()
                        ->prefix(__('filament-dashboards::dashboards.dates.from'))
                        ->default($range->start->toDateString())
                        ->afterStateUpdated(fn (Set $set) => $set('preset', null)),
                    DatePicker::make('endDate')
                        ->hiddenLabel()
                        ->prefix(__('filament-dashboards::dashboards.dates.until'))
                        ->default($range->end->toDateString())
                        ->afterStateUpdated(fn (Set $set) => $set('preset', null)),
                ])->from('lg')->columnSpanFull(),
            ]);
    }
}

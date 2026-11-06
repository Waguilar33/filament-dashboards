<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Pages\Concerns;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Flex;
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

        foreach ($plugin->getDatePresets() as $days) {
            $presets[$days] = $days === 1
                ? __('filament-dashboards::dashboards.dates.today')
                : trans_choice('filament-dashboards::dashboards.dates.days', $days, ['count' => $days]);
        }

        $presets[ResolveDateRange::CUSTOM] = __('filament-dashboards::dashboards.dates.custom');

        return $schema
            ->components([
                ToggleButtons::make('preset')
                    ->hiddenLabel()
                    ->options($presets)
                    ->default($plugin->getDefaultDatePreset())
                    ->grouped()
                    ->columnSpanFull()
                    ->afterStateUpdated(function (Set $set, mixed $state, mixed $old): void {
                        if ($state !== ResolveDateRange::CUSTOM) {
                            $set('startDate', null);
                            $set('endDate', null);

                            return;
                        }

                        $range = app(ResolveDateRange::class)->handle(['preset' => $old]);
                        $set('startDate', $range->start->toDateString());
                        $set('endDate', $range->end->toDateString());
                    }),
                Flex::make([
                    DatePicker::make('startDate')
                        ->hiddenLabel()
                        ->prefix(__('filament-dashboards::dashboards.dates.from')),
                    DatePicker::make('endDate')
                        ->hiddenLabel()
                        ->prefix(__('filament-dashboards::dashboards.dates.until')),
                ])
                    ->from('sm')
                    ->visible(fn (Get $get): bool => $get('preset') === ResolveDateRange::CUSTOM)
                    ->columnSpanFull(),
            ]);
    }
}

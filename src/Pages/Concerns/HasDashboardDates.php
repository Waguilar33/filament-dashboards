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
                            if ($state !== ResolveDateRange::CUSTOM) {
                                $set('startDate', null);
                                $set('endDate', null);

                                return;
                            }

                            $range = app(ResolveDateRange::class)->handle(['preset' => $old]);
                            $set('startDate', $range->start->toDateString());
                            $set('endDate', $range->end->toDateString());
                        }),
                    FusedGroup::make([
                        DatePicker::make('startDate')
                            ->hiddenLabel()
                            ->live(condition: false)
                            ->prefix(__('filament-dashboards::dashboards.dates.from')),
                        DatePicker::make('endDate')
                            ->hiddenLabel()
                            ->live(condition: false)
                            ->prefix(__('filament-dashboards::dashboards.dates.until'))
                            ->suffixAction(Action::make('applyDates')
                                ->label(__('filament-dashboards::dashboards.dates.apply'))
                                ->button()
                                ->alpineClickHandler('$wire.applyDashboardDates()')),
                    ])
                        ->columns(2)
                        ->extraAttributes(['class' => 'fi-dashboards-dates fi-dashboards-custom-dates'])
                        ->grow(false)
                        ->visible(fn (Get $get): bool => $get('preset') === ResolveDateRange::CUSTOM),
                ])
                    ->from('lg')
                    ->columnSpanFull(),
            ]);
    }

    public function applyDashboardDates(): void {}
}

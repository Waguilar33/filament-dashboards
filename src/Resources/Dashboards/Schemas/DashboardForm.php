<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Resources\Dashboards\Schemas;

use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Waguilar\FilamentDashboards\Enums\NavigationPosition;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;

class DashboardForm
{
    public static function configure(Schema $schema): Schema
    {
        return FilamentDashboardsPlugin::get()->modifyDashboardForm($schema->components([
            TextInput::make('name')
                ->label(__('filament-dashboards::dashboards.fields.name'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Get $get, Set $set, ?Dashboard $record): void {
                    if ($record === null && blank($get('slug'))) {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            TextInput::make('slug')
                ->label(__('filament-dashboards::dashboards.fields.slug'))
                ->required()
                ->alphaDash()
                ->maxLength(255)
                ->rule(fn (?Dashboard $record): Closure => self::uniqueSlug($record)),
            Toggle::make('show_in_navigation')
                ->label(__('filament-dashboards::dashboards.fields.show_in_navigation'))
                ->live(),
            Select::make('navigation_icon')
                ->label(__('filament-dashboards::dashboards.fields.navigation_icon'))
                ->options(self::iconOptions())
                ->searchable()
                ->visible(fn (Get $get): bool => (bool) $get('show_in_navigation')),
            TextInput::make('navigation_label')
                ->label(__('filament-dashboards::dashboards.fields.navigation_label'))
                ->helperText(__('filament-dashboards::dashboards.fields.navigation_label_helper'))
                ->maxLength(255)
                ->visible(fn (Get $get): bool => (bool) $get('show_in_navigation')),
            TextInput::make('navigation_group')
                ->label(__('filament-dashboards::dashboards.fields.navigation_group'))
                ->maxLength(255)
                ->visible(fn (Get $get): bool => (bool) $get('show_in_navigation')),
            ToggleButtons::make('navigation_position')
                ->label(__('filament-dashboards::dashboards.fields.navigation_position'))
                ->options([
                    NavigationPosition::Start->value => __('filament-dashboards::dashboards.navigation_positions.start'),
                    NavigationPosition::End->value => __('filament-dashboards::dashboards.navigation_positions.end'),
                ])
                ->default(NavigationPosition::End->value)
                ->inline()
                ->visible(fn (Get $get): bool => (bool) $get('show_in_navigation')),
        ]));
    }

    public static function uniqueSlug(?Dashboard $ignore): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            $taken = Dashboard::query()
                ->inScope(FilamentDashboardsPlugin::currentPanel()->getId(), Filament::getTenant())
                ->where('slug', $value)
                ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore?->getKey()))
                ->exists();

            if ($taken) {
                $fail(__('filament-dashboards::dashboards.validation.slug_taken'));
            }
        };
    }

    /**
     * @return array<string, string>
     */
    protected static function iconOptions(): array
    {
        $options = [];

        foreach (Heroicon::cases() as $icon) {
            if (str_starts_with($icon->value, 'o-')) {
                $options['heroicon-'.$icon->value] = Str::headline(substr($icon->value, 2));
            }
        }

        return $options;
    }
}

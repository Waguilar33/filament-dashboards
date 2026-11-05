<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Filament\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Component;
use Waguilar\FilamentDashboards\Actions\CloneDashboard;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;
use Waguilar\FilamentDashboards\Resources\Dashboards\Schemas\DashboardForm;

class DuplicateDashboardAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'duplicate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-dashboards::dashboards.actions.duplicate'))
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->visible(fn (): bool => DashboardResource::canCreate())
            ->modalWidth(Width::Medium)
            ->modalHeading(__('filament-dashboards::dashboards.modals.clone.heading'))
            ->modalDescription(__('filament-dashboards::dashboards.modals.clone.description'))
            ->fillForm(fn (Dashboard $record): array => [
                'name' => __('filament-dashboards::dashboards.modals.clone.name', ['name' => $record->name]),
                'slug' => $record->slug.'-'.Str::lower(Str::random(4)),
            ])
            ->schema([
                TextInput::make('name')
                    ->label(__('filament-dashboards::dashboards.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label(__('filament-dashboards::dashboards.fields.slug'))
                    ->required()
                    ->alphaDash()
                    ->maxLength(255)
                    ->rule(fn (): Closure => DashboardForm::uniqueSlug(null)),
            ])
            ->action(function (array $data, Dashboard $record, Component $livewire): void {
                $clone = app(CloneDashboard::class)->handle($record, DashboardResource::user(), Filament::getTenant(), Arr::string($data, 'name'), Arr::string($data, 'slug'));

                $livewire->redirect(DashboardResource::getUrl('view', ['record' => $clone]), navigate: true);
            });
    }
}

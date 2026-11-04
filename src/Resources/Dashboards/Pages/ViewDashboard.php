<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Resources\Dashboards\Pages;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Waguilar\FilamentDashboards\Actions\AddDashboardWidget;
use Waguilar\FilamentDashboards\Actions\CloneDashboard;
use Waguilar\FilamentDashboards\Actions\SaveDashboardLayout;
use Waguilar\FilamentDashboards\Actions\SetHomeDashboard;
use Waguilar\FilamentDashboards\Filament\Actions\ShareAction;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Forms\WidgetPicker;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Pages\Concerns\RendersDashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;
use Waguilar\FilamentDashboards\Resources\Dashboards\Schemas\DashboardForm;

class ViewDashboard extends Page
{
    use InteractsWithRecord;
    use RendersDashboard;

    protected static string $resource = DashboardResource::class;

    public bool $isEditingLayout = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(DashboardResource::canView($this->getRecord()), 403);
    }

    public function getTitle(): string
    {
        return $this->getDashboard()->name;
    }

    public function getDashboard(): Dashboard
    {
        /** @var Dashboard */
        return $this->getRecord();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components($this->dashboardComponents(
            $this->getDashboard(),
            fn (): bool => $this->isEditingLayout,
            $this->canEdit() ? $this->addWidgetAction() : null,
        ));
    }

    /**
     * @param  array<mixed>  $layout
     */
    public function saveDashboardLayout(array $layout): void
    {
        abort_unless($this->canEdit(), 403);

        app(SaveDashboardLayout::class)->handle($this->getDashboard(), $layout);
    }

    public function removeDashboardItem(int $item): void
    {
        abort_unless($this->canEdit(), 403);

        $this->getDashboard()->items()->whereKey($item)->delete();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addWidgetAction(),
            $this->shareAction(),
            ActionGroup::make([
                $this->editLayoutAction(),
                $this->settingsAction(),
                $this->cloneAction(),
                $this->setHomeAction(),
                $this->deleteAction(),
            ]),
        ];
    }

    protected function addWidgetAction(): Action
    {
        $widgets = FilamentDashboardsPlugin::get()->getAvailableWidgets(FilamentDashboardsPlugin::currentPanel(), DashboardResource::user());

        return Action::make('addWidget')
            ->label(__('filament-dashboards::dashboards.actions.add_widget'))
            ->visible($this->canEdit())
            ->slideOver()
            ->modalHeading(__('filament-dashboards::dashboards.modals.add_widget.heading'))
            ->modalDescription(__('filament-dashboards::dashboards.modals.add_widget.description'))
            ->modalSubmitActionLabel(__('filament-dashboards::dashboards.actions.add_widget'))
            ->modalWidth(Width::SevenExtraLarge)
            ->schema([
                WidgetPicker::make('widget')
                    ->hiddenLabel()
                    ->required()
                    ->sections([[
                        'heading' => __('filament-dashboards::dashboards.picker.quick.heading'),
                        'description' => __('filament-dashboards::dashboards.picker.quick.description'),
                        'carousel' => true,
                        'options' => array_map(fn (string $widget): array => [
                            'label' => $widget::dashboardLabel(),
                            'description' => $widget::dashboardDescription(),
                            'preview' => $widget::dashboardPreview(),
                            'kind' => match (true) {
                                is_subclass_of($widget, StatsOverviewWidget::class) => 'stats',
                                is_subclass_of($widget, ChartWidget::class) => 'line',
                                is_subclass_of($widget, TableWidget::class) => 'table',
                                default => 'widget',
                            },
                        ], $widgets),
                    ]]),
            ])
            ->action(fn (array $data) => app(AddDashboardWidget::class)->handle($this->getDashboard(), $widgets[Arr::string($data, 'widget')]));
    }

    protected function shareAction(): ShareAction
    {
        return ShareAction::make()->record($this->getDashboard());
    }

    protected function editLayoutAction(): Action
    {
        return Action::make('editLayout')
            ->label(fn (): string => $this->isEditingLayout
                ? __('filament-dashboards::dashboards.actions.done')
                : __('filament-dashboards::dashboards.actions.edit_layout'))
            ->visible($this->canEdit())
            ->action(fn () => $this->isEditingLayout = ! $this->isEditingLayout);
    }

    protected function settingsAction(): EditAction
    {
        return EditAction::make('settings')
            ->label(__('filament-dashboards::dashboards.actions.settings'))
            ->icon(null)
            ->visible($this->canEdit())
            ->slideOver()
            ->record($this->getDashboard())
            ->schema(fn (Schema $schema): Schema => DashboardForm::configure($schema))
            ->successRedirectUrl(fn (Dashboard $record): string => DashboardResource::getUrl('view', ['record' => $record]));
    }

    protected function cloneAction(): Action
    {
        return Action::make('clone')
            ->label(__('filament-dashboards::dashboards.actions.clone'))
            ->visible(DashboardResource::canCreate())
            ->modalWidth(Width::Medium)
            ->modalHeading(__('filament-dashboards::dashboards.modals.clone.heading'))
            ->modalDescription(__('filament-dashboards::dashboards.modals.clone.description'))
            ->fillForm(fn (): array => [
                'name' => __('filament-dashboards::dashboards.modals.clone.name', ['name' => $this->getDashboard()->name]),
                'slug' => $this->getDashboard()->slug.'-'.Str::lower(Str::random(4)),
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
            ->action(function (array $data): void {
                $clone = app(CloneDashboard::class)->handle($this->getDashboard(), DashboardResource::user(), Filament::getTenant(), Arr::string($data, 'name'), Arr::string($data, 'slug'));

                $this->redirect(DashboardResource::getUrl('view', ['record' => $clone]), navigate: true);
            });
    }

    protected function setHomeAction(): Action
    {
        return Action::make('setHome')
            ->label(__('filament-dashboards::dashboards.actions.set_home'))
            ->action(function (): void {
                app(SetHomeDashboard::class)->handle($this->getDashboard(), DashboardResource::user(), Filament::getTenant());

                Notification::make()->success()->title(__('filament-dashboards::dashboards.notifications.home_set'))->send();
            });
    }

    protected function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->record($this->getDashboard())
            ->visible($this->getDashboard()->canBeDeletedBy(DashboardResource::user()))
            ->successRedirectUrl(DashboardResource::getUrl());
    }

    protected function canEdit(): bool
    {
        return $this->getDashboard()->canBeEditedBy(DashboardResource::user());
    }
}

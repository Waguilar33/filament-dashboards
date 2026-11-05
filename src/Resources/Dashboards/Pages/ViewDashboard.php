<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Resources\Dashboards\Pages;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Waguilar\FilamentDashboards\Actions\AddDashboardWidget;
use Waguilar\FilamentDashboards\Actions\CloneDashboard;
use Waguilar\FilamentDashboards\Actions\DuplicateDashboardItem;
use Waguilar\FilamentDashboards\Actions\FindHomeDashboard;
use Waguilar\FilamentDashboards\Actions\RemoveHomeDashboard;
use Waguilar\FilamentDashboards\Actions\SaveBuilderWidget;
use Waguilar\FilamentDashboards\Actions\SaveDashboardLayout;
use Waguilar\FilamentDashboards\Actions\SetHomeDashboard;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Filament\Actions\ShareAction;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Forms\WidgetPicker;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;
use Waguilar\FilamentDashboards\Pages\Concerns\HasDashboardDates;
use Waguilar\FilamentDashboards\Pages\Concerns\RendersDashboard;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Schemas\BuilderWidgetForm;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;
use Waguilar\FilamentDashboards\Resources\Dashboards\Schemas\DashboardForm;

class ViewDashboard extends Page
{
    public const NEW_PREFIX = 'new:';

    use HasDashboardDates;
    use InteractsWithRecord;
    use RendersDashboard;

    protected static string $resource = DashboardResource::class;

    #[Url(as: 'editing')]
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
        return $schema->components([
            ...($this->isEditingLayout && $this->canEdit() ? [
                Section::make(__('filament-dashboards::dashboards.edit_mode.heading'))
                    ->key('editMode')
                    ->compact()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->headerActions([$this->doneEditingAction(), $this->addWidgetAction()]),
            ] : []),
            ...$this->dashboardComponents(
                $this->getDashboard(),
                fn (): bool => $this->isEditingLayout,
                $this->canEdit() && ! $this->isEditingLayout ? $this->addWidgetAction() : null,
            ),
        ]);
    }

    /**
     * @param  array<mixed>  $layout
     */
    public function saveDashboardLayout(array $layout): void
    {
        abort_unless($this->canEdit(), 403);

        app(SaveDashboardLayout::class)->handle($this->getDashboard(), $layout);
    }

    public function duplicateItemAction(): Action
    {
        return Action::make('duplicateItem')
            ->visible($this->canEdit())
            ->action(function (array $arguments): void {
                $item = $this->editableItem($arguments);
                $user = DashboardResource::user();
                $builderWidget = BuilderWidget::query()
                    ->where('panel', $this->getDashboard()->panel)
                    ->visibleTo($user)
                    ->get()
                    ->first(fn (BuilderWidget $widget): bool => $widget->dashboardKey() === $item->widget);

                abort_unless(
                    $builderWidget instanceof BuilderWidget
                        || array_key_exists($item->widget, FilamentDashboardsPlugin::get()->getAvailableWidgets(FilamentDashboardsPlugin::currentPanel(), $user)),
                    403,
                );

                app(DuplicateDashboardItem::class)->handle($this->getDashboard(), $item, $builderWidget, $user, Filament::getTenant());

                $this->reloadDashboard();
            });
    }

    public function removeItemAction(): Action
    {
        return Action::make('removeItem')
            ->visible($this->canEdit())
            ->action(function (array $arguments): void {
                $this->editableItem($arguments)->delete();

                $this->reloadDashboard();
            });
    }

    /**
     * @param  array<mixed>  $arguments
     */
    protected function editableItem(array $arguments): DashboardItem
    {
        abort_unless($this->canEdit(), 403);

        return $this->getDashboard()->items()->whereKey($arguments['item'] ?? null)->firstOrFail();
    }

    protected function reloadDashboard(): void
    {
        $this->redirect(DashboardResource::getUrl('view', array_filter([
            'record' => $this->getDashboard(),
            'editing' => $this->isEditingLayout ? 1 : null,
        ])), navigate: true);
    }

    protected function getHeaderActions(): array
    {
        return FilamentDashboardsPlugin::get()->arrangeHeaderActions([
            'edit' => $this->editLayoutAction(),
            'share' => $this->shareAction(),
            'settings' => $this->settingsAction(),
            'duplicate' => $this->cloneAction(),
            'setAsHome' => $this->setHomeAction(),
            'removeHome' => $this->removeHomeAction(),
            'delete' => $this->deleteAction(),
        ]);
    }

    protected function addWidgetAction(): Action
    {
        $plugin = FilamentDashboardsPlugin::get();
        $user = DashboardResource::user();
        $widgets = $plugin->getAvailableWidgets(FilamentDashboardsPlugin::currentPanel(), $user);
        $sources = $plugin->getDataSourcesFor($user, Filament::getTenant());
        $builderWidgets = BuilderWidget::query()
            ->when($sources === [], fn (Builder $query): Builder => $query->whereKey([]))
            ->inScope(FilamentDashboardsPlugin::currentPanel()->getId(), Filament::getTenant())
            ->visibleTo($user)
            ->orderBy('name')
            ->get()
            ->filter(fn (BuilderWidget $widget): bool => array_diff($widget->sourceKeys(), array_keys($sources)) === [])
            ->keyBy(fn (BuilderWidget $widget): string => $widget->dashboardKey());

        $quick = array_map(fn (string $widget): array => [
            'label' => $widget::dashboardLabel(),
            'description' => $widget::dashboardDescription(),
            'preview' => $widget::dashboardPreview(),
            'kind' => match (true) {
                is_subclass_of($widget, StatsOverviewWidget::class) => 'stats',
                is_subclass_of($widget, ChartWidget::class) => 'line',
                is_subclass_of($widget, TableWidget::class) => 'table',
                default => 'widget',
            },
        ], $widgets);

        foreach ($builderWidgets as $key => $widget) {
            $quick[$key] = ['label' => $widget->name, 'description' => $widget->type->getLabel(), 'preview' => null, 'kind' => $widget->type->preview()];
        }

        $sections = [[
            'heading' => __('filament-dashboards::dashboards.picker.quick.heading'),
            'description' => __('filament-dashboards::dashboards.picker.quick.description'),
            'carousel' => true,
            'options' => $quick,
        ]];

        if ($sources !== []) {
            $new = [];

            foreach (BuilderWidgetType::cases() as $type) {
                $new[self::NEW_PREFIX.$type->value] = ['label' => $type->getLabel(), 'description' => $type->getDescription(), 'preview' => null, 'kind' => $type->preview()];
            }

            $sections[] = [
                'heading' => __('filament-dashboards::dashboards.builder.new.heading'),
                'description' => __('filament-dashboards::dashboards.builder.new.description'),
                'carousel' => false,
                'options' => $new,
            ];
        }

        return $plugin->modifyAddWidgetAction(Action::make('addWidget')
            ->label(__('filament-dashboards::dashboards.actions.add_widget'))
            ->visible($this->canEdit())
            ->slideOver()
            ->modalHeading(__('filament-dashboards::dashboards.modals.add_widget.heading'))
            ->modalDescription(__('filament-dashboards::dashboards.modals.add_widget.description'))
            ->modalSubmitActionLabel(__('filament-dashboards::dashboards.actions.add_widget'))
            ->modalWidth(Width::FourExtraLarge)
            ->schema([
                WidgetPicker::make('widget')
                    ->hiddenLabel()
                    ->required()
                    ->sections($sections),
            ])
            ->action(function (array $data, Action $action) use ($widgets, $builderWidgets): void {
                $key = Arr::string($data, 'widget');

                if (str_starts_with($key, self::NEW_PREFIX)) {
                    $this->replaceMountedAction('buildWidget', ['type' => substr($key, strlen(self::NEW_PREFIX))]);

                    return;
                }

                $builderWidget = $builderWidgets->get($key);

                app(AddDashboardWidget::class)->handle(
                    $this->getDashboard(),
                    $key,
                    $builderWidget instanceof BuilderWidget ? $builderWidget->type->defaultSize() : $widgets[$key]::dashboardSize(),
                );

                $this->reloadDashboard();
            }));
    }

    public function buildWidgetAction(): Action
    {
        return Action::make('buildWidget')
            ->visible($this->canEdit())
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading(fn (array $arguments): string => BuilderWidgetType::from(Arr::string($arguments, 'type'))->getLabel())
            ->modalSubmitActionLabel(__('filament-dashboards::dashboards.actions.add_widget'))
            ->schema(fn (array $arguments): array => BuilderWidgetForm::components(BuilderWidgetType::from(Arr::string($arguments, 'type'))))
            ->action(function (array $data, array $arguments): void {
                $type = BuilderWidgetType::from(Arr::string($arguments, 'type'));
                $widget = app(SaveBuilderWidget::class)->handle(
                    SaveBuilderWidget::newFor(DashboardResource::user(), Filament::getTenant(), FilamentDashboardsPlugin::currentPanel()->getId()),
                    $type,
                    $data,
                );

                app(AddDashboardWidget::class)->handle($this->getDashboard(), $widget->dashboardKey(), $type->defaultSize());

                $this->reloadDashboard();
            });
    }

    public function editWidgetAction(): Action
    {
        return Action::make('editWidget')
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading(fn (array $arguments): string => $this->editableWidget($arguments)->name)
            ->fillForm(fn (array $arguments): array => $this->editableWidget($arguments)->only(['name', 'refresh_interval', 'config']))
            ->schema(fn (array $arguments): array => BuilderWidgetForm::components($this->editableWidget($arguments)->type))
            ->action(function (array $data, array $arguments): void {
                $widget = $this->editableWidget($arguments);

                app(SaveBuilderWidget::class)->handle($widget, $widget->type, $data);

                $this->reloadDashboard();
            });
    }

    /**
     * @param  array<mixed>  $arguments
     */
    protected function editableWidget(array $arguments): BuilderWidget
    {
        $widget = BuilderWidget::query()->find($arguments['widget'] ?? null);

        abort_unless($widget instanceof BuilderWidget && $widget->canBeEditedBy(DashboardResource::user()), 403);

        return $widget;
    }

    protected function shareAction(): ShareAction
    {
        return FilamentDashboardsPlugin::get()->modifyShareAction(ShareAction::make()->record($this->getDashboard())->color('gray'));
    }

    protected function editLayoutAction(): Action
    {
        return Action::make('edit')
            ->label(__('filament-dashboards::dashboards.actions.edit'))
            ->color('primary')
            ->visible($this->canEdit() && ! $this->isEditingLayout)
            ->action(function (): void {
                $this->isEditingLayout = true;

                $this->reloadDashboard();
            });
    }

    protected function doneEditingAction(): Action
    {
        return Action::make('doneEditing')
            ->label(__('filament-dashboards::dashboards.actions.done_editing'))
            ->color('gray')
            ->action(function (): void {
                $this->isEditingLayout = false;

                $this->reloadDashboard();
            });
    }

    protected function settingsAction(): EditAction
    {
        return FilamentDashboardsPlugin::get()->modifySettingsAction(EditAction::make('settings')
            ->label(__('filament-dashboards::dashboards.actions.settings'))
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->visible($this->canEdit())
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->record($this->getDashboard())
            ->schema(fn (Schema $schema): Schema => DashboardForm::configure($schema))
            ->successRedirectUrl(fn (Dashboard $record): string => DashboardResource::getUrl('view', ['record' => $record])));
    }

    protected function cloneAction(): Action
    {
        return Action::make('duplicate')
            ->label(__('filament-dashboards::dashboards.actions.clone'))
            ->icon(Heroicon::OutlinedDocumentDuplicate)
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
        return Action::make('setAsHome')
            ->label(__('filament-dashboards::dashboards.actions.set_home'))
            ->icon(Heroicon::OutlinedHome)
            ->hidden(fn (): bool => $this->isHome())
            ->action(function (): void {
                app(SetHomeDashboard::class)->handle($this->getDashboard(), DashboardResource::user(), Filament::getTenant());

                Notification::make()->success()->title(__('filament-dashboards::dashboards.notifications.home_set'))->send();
            });
    }

    protected function removeHomeAction(): Action
    {
        return Action::make('removeHome')
            ->label(__('filament-dashboards::dashboards.actions.remove_home'))
            ->icon(Heroicon::OutlinedHome)
            ->visible(fn (): bool => $this->isHome())
            ->action(function (): void {
                app(RemoveHomeDashboard::class)->handle(DashboardResource::user(), FilamentDashboardsPlugin::currentPanel()->getId(), Filament::getTenant());

                Notification::make()->success()->title(__('filament-dashboards::dashboards.notifications.home_removed'))->send();
            });
    }

    protected function isHome(): bool
    {
        return app(FindHomeDashboard::class)->handle(DashboardResource::user(), FilamentDashboardsPlugin::currentPanel()->getId(), Filament::getTenant())?->is($this->getDashboard()) === true;
    }

    protected function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->icon(Heroicon::OutlinedTrash)
            ->record($this->getDashboard())
            ->visible($this->getDashboard()->canBeDeletedBy(DashboardResource::user()))
            ->successRedirectUrl(DashboardResource::getUrl());
    }

    protected function canEdit(): bool
    {
        return $this->getDashboard()->canBeEditedBy(DashboardResource::user());
    }
}

<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Resources\Dashboards\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Waguilar\FilamentDashboards\Actions\AddDashboardWidget;
use Waguilar\FilamentDashboards\Actions\DuplicateDashboardItem;
use Waguilar\FilamentDashboards\Actions\SaveBuilderWidget;
use Waguilar\FilamentDashboards\Actions\SaveCardWidths;
use Waguilar\FilamentDashboards\Actions\SaveDashboardLayout;
use Waguilar\FilamentDashboards\Commands\SendDashboardNotificationsCommand;
use Waguilar\FilamentDashboards\Enums\AlertOperator;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Enums\DigestFrequency;
use Waguilar\FilamentDashboards\Filament\Actions\BuildWidgetAction;
use Waguilar\FilamentDashboards\Filament\Actions\DashboardSettingsAction;
use Waguilar\FilamentDashboards\Filament\Actions\DuplicateDashboardAction;
use Waguilar\FilamentDashboards\Filament\Actions\RemoveHomeAction;
use Waguilar\FilamentDashboards\Filament\Actions\SetHomeAction;
use Waguilar\FilamentDashboards\Filament\Actions\ShareAction;
use Waguilar\FilamentDashboards\Filament\Actions\TransferOwnershipAction;
use Waguilar\FilamentDashboards\Filament\Actions\WidgetHistoryAction;
use Waguilar\FilamentDashboards\FilamentDashboardsIcon;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Forms\WidgetPicker;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardAlert;
use Waguilar\FilamentDashboards\Models\DashboardItem;
use Waguilar\FilamentDashboards\Models\DashboardSubscription;
use Waguilar\FilamentDashboards\Pages\Concerns\HasDashboardDates;
use Waguilar\FilamentDashboards\Pages\Concerns\RendersDashboard;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\BuilderWidgetResource;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Schemas\BuilderWidgetForm;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class ViewDashboard extends Page
{
    public const NEW_PREFIX = 'new:';

    use HasDashboardDates;
    use InteractsWithRecord;
    use RendersDashboard;

    protected static string $resource = DashboardResource::class;

    protected ?bool $canEdit = null;

    #[Url(as: 'editing')]
    public bool $isEditingLayout = false;

    #[Url(as: 'print')]
    public bool $isPrinting = false;

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        return $record instanceof Dashboard ? DashboardResource::canView($record) : parent::canAccess($parameters);
    }

    public static function authorizeResourceAccess(): void {}

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
                    ->description(new HtmlString('<span class="fi-dashboards-narrow-notice">'.e(__('filament-dashboards::dashboards.edit_mode.narrow')).'</span>'))
                    ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::EDIT))
                    ->headerActions([$this->doneEditingAction(), $this->addWidgetAction()]),
            ] : []),
            ...$this->dashboardComponents(
                $this->getDashboard(),
                fn (): bool => $this->isEditingLayout,
                $this->canEdit() && ! $this->isEditingLayout ? $this->addWidgetAction(...) : null,
            ),
        ]);
    }

    /**
     * @param  array<mixed>  $layout
     */
    #[Renderless]
    public function saveDashboardLayout(array $layout): void
    {
        abort_unless($this->canEdit(), 403);

        app(SaveDashboardLayout::class)->handle($this->getDashboard(), $layout);
    }

    /**
     * @param  array<mixed>  $widths
     */
    public function saveCardWidths(int $item, array $widths): void
    {
        app(SaveCardWidths::class)->handle($this->editableItem(['item' => $item]), $widths);
    }

    public function duplicateItemAction(): Action
    {
        return FilamentDashboardsPlugin::get()->modifyAction(Action::make('duplicateItem')
            ->label(__('filament-dashboards::dashboards.actions.duplicate'))
            ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::DUPLICATE))
            ->visible($this->canEdit())
            ->action(function (array $arguments): void {
                $item = $this->editableItem($arguments);
                $user = DashboardResource::user();
                $builderWidget = BuilderWidget::query()
                    ->where('panel', $this->getDashboard()->panel)
                    ->visibleTo($user)
                    ->get()
                    ->first(fn (BuilderWidget $widget): bool => $widget->dashboardKey() === $item->widget);

                abort_if($builderWidget instanceof BuilderWidget && ! BuilderWidgetResource::canCreate(), 403);
                abort_unless(
                    $builderWidget instanceof BuilderWidget
                        || array_key_exists($item->widget, FilamentDashboardsPlugin::get()->getAvailableWidgets(FilamentDashboardsPlugin::currentPanel(), $user)),
                    403,
                );

                app(DuplicateDashboardItem::class)->handle($this->getDashboard(), $item, $builderWidget, $user, Filament::getTenant());

                $this->reloadDashboard();
            }), 'dashboard.duplicateItem');
    }

    public function removeItemAction(): Action
    {
        return FilamentDashboardsPlugin::get()->modifyAction(Action::make('removeItem')
            ->label(__('filament-dashboards::dashboards.actions.remove'))
            ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::REMOVE))
            ->visible($this->canEdit())
            ->requiresConfirmation()
            ->color('danger')
            ->modalHeading(__('filament-dashboards::dashboards.modals.remove_item.heading'))
            ->modalDescription(__('filament-dashboards::dashboards.modals.remove_item.description'))
            ->modalSubmitActionLabel(__('filament-dashboards::dashboards.actions.remove'))
            ->action(function (array $arguments): void {
                $this->editableItem($arguments)->delete();

                $this->reloadDashboard();
            }), 'dashboard.removeItem');
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
            'emailMe' => $this->emailMeAction(),
            'alerts' => $this->alertsAction(),
            'print' => FilamentDashboardsPlugin::get()->modifyAction(Action::make('print')
                ->label(__('filament-dashboards::dashboards.actions.print'))
                ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::PRINT))
                ->url(fn (): string => DashboardResource::getUrl('view', ['record' => $this->getDashboard(), 'print' => 1])), 'dashboard.print'),
            'duplicate' => $this->cloneAction(),
            'transfer' => FilamentDashboardsPlugin::get()->modifyAction(TransferOwnershipAction::make()->record($this->getDashboard()), 'dashboard.transfer'),
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
            ->get()
            ->sortBy(fn (BuilderWidget $widget): string => mb_strtolower($widget->name))
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

        if (BuilderWidgetResource::canCreate()) {
            $sections[] = BuildWidgetAction::pickerSection(self::NEW_PREFIX);
        }

        return FilamentDashboardsPlugin::get()->modifyAction($plugin->modifyAddWidgetAction(Action::make('addWidget')
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
                    DashboardResource::user(),
                );

                $this->reloadDashboard();
            })), 'dashboard.addWidget');
    }

    public function buildWidgetAction(): Action
    {
        return FilamentDashboardsPlugin::get()->modifyAction(BuildWidgetAction::make()
            ->visible($this->canEdit() && BuilderWidgetResource::canCreate())
            ->modalSubmitActionLabel(__('filament-dashboards::dashboards.actions.add_widget'))
            ->after(function (BuilderWidget $record): void {
                app(AddDashboardWidget::class)->handle($this->getDashboard(), $record->dashboardKey(), $record->type->defaultSize(), DashboardResource::user());

                $this->reloadDashboard();
            }), 'dashboard.buildWidget');
    }

    public function editWidgetAction(): Action
    {
        return FilamentDashboardsPlugin::get()->modifyAction(Action::make('editWidget')
            ->label(__('filament-dashboards::dashboards.actions.edit'))
            ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::EDIT))
            ->visible($this->canEdit())
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading(fn (array $arguments): string => $this->editableWidget($arguments)->name)
            ->modalDescription(fn (array $arguments): string => trans_choice('filament-dashboards::dashboards.modals.edit_widget.used_on', $count = $this->editableWidget($arguments)->placements()->distinct()->count('dashboard_id'), ['count' => $count]))
            ->fillForm(fn (array $arguments): array => BuilderWidgetForm::fill($this->editableWidget($arguments)))
            ->schema(fn (array $arguments): array => BuilderWidgetForm::components($this->editableWidget($arguments)->type))
            ->extraModalFooterActions(fn (Action $action, array $arguments): array => BuilderWidgetResource::canCreate() && filled($arguments['item'] ?? null) ? [
                $action->makeModalSubmitAction('saveAsCopy', arguments: ['copy' => true])
                    ->label(__('filament-dashboards::dashboards.actions.save_as_copy'))
                    ->color('gray'),
            ] : [])
            ->action(function (array $data, array $arguments): void {
                $widget = $this->editableWidget($arguments);

                if ((bool) ($arguments['copy'] ?? false)) {
                    abort_unless(BuilderWidgetResource::canCreate(), 403);

                    $item = $this->editableItem($arguments);
                    $copy = app(SaveBuilderWidget::class)->handle(
                        SaveBuilderWidget::newFor(DashboardResource::user(), Filament::getTenant(), $widget->panel),
                        $widget->type,
                        $data,
                        DashboardResource::user(),
                    );
                    $item->update(['widget' => $copy->dashboardKey(), 'shares_widget' => true]);
                } else {
                    app(SaveBuilderWidget::class)->handle($widget, $widget->type, $data, DashboardResource::user());
                }

                $this->reloadDashboard();
            }), 'dashboard.editWidget');
    }

    protected function emailMeAction(): Action
    {
        $find = fn (): ?DashboardSubscription => DashboardSubscription::query()
            ->where('panel', $this->getDashboard()->panel)
            ->where('dashboard_id', $this->getDashboard()->getKey())
            ->whereMorphedTo('user', DashboardResource::user())
            ->where('tenant_type', Filament::getTenant()?->getMorphClass())
            ->where('tenant_id', Filament::getTenant()?->getKey())
            ->first();

        return FilamentDashboardsPlugin::get()->modifyAction(Action::make('emailMe')
            ->label(__('filament-dashboards::dashboards.actions.email_me'))
            ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::EMAIL))
            ->visible(FilamentDashboardsPlugin::get()->hasScheduledNotifications())
            ->modalWidth(Width::Medium)
            ->modalDescription(__('filament-dashboards::dashboards.digest.description', ['hour' => SendDashboardNotificationsCommand::SEND_HOUR]))
            ->modalSubmitActionLabel(__('filament-dashboards::dashboards.actions.save'))
            ->fillForm(fn (): array => ['frequency' => $find()?->frequency->value])
            ->schema([
                Select::make('frequency')
                    ->label(__('filament-dashboards::dashboards.digest.frequency'))
                    ->placeholder(__('filament-dashboards::dashboards.digest.off'))
                    ->options(collect(DigestFrequency::cases())->mapWithKeys(fn (DigestFrequency $frequency): array => [$frequency->value => $frequency->getLabel()])->all()),
            ])
            ->action(function (array $data) use ($find): void {
                $frequency = DigestFrequency::tryFrom(is_string($data['frequency'] ?? null) ? $data['frequency'] : '');
                $subscription = $find();

                if ($frequency === null) {
                    $subscription?->delete();

                    return;
                }

                $subscription ??= new DashboardSubscription([
                    'panel' => $this->getDashboard()->panel,
                    'dashboard_id' => $this->getDashboard()->getKey(),
                ]);
                $subscription->user()->associate(DashboardResource::user());
                $subscription->tenant()->associate(Filament::getTenant());
                $subscription->frequency = $frequency;
                $subscription->save();
            })
            ->successNotificationTitle(__('filament-dashboards::dashboards.notifications.saved')), 'dashboard.emailMe');
    }

    protected function alertsAction(): Action
    {
        $mine = fn (): Builder => DashboardAlert::query()
            ->where('dashboard_id', $this->getDashboard()->getKey())
            ->whereMorphedTo('user', DashboardResource::user())
            ->where('tenant_type', Filament::getTenant()?->getMorphClass())
            ->where('tenant_id', Filament::getTenant()?->getKey());

        return FilamentDashboardsPlugin::get()->modifyAction(Action::make('alerts')
            ->label(__('filament-dashboards::dashboards.actions.alerts'))
            ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::ALERTS))
            ->visible(FilamentDashboardsPlugin::get()->hasScheduledNotifications())
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->modalDescription(__('filament-dashboards::dashboards.alerts.description', ['hour' => SendDashboardNotificationsCommand::SEND_HOUR]))
            ->modalSubmitActionLabel(__('filament-dashboards::dashboards.actions.save'))
            ->fillForm(fn (): array => ['alerts' => $mine()->get()->map(fn (DashboardAlert $alert): array => [
                'card' => "{$alert->builder_widget_id}:{$alert->card_key}",
                'operator' => $alert->operator->value,
                'threshold' => $alert->threshold,
            ])->all()])
            ->schema(fn (): array => [
                Repeater::make('alerts')
                    ->hiddenLabel()
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->compact()
                    ->addActionLabel(__('filament-dashboards::dashboards.alerts.add'))
                    ->table([
                        TableColumn::make(__('filament-dashboards::dashboards.alerts.card'))->markAsRequired()->alignStart(),
                        TableColumn::make(__('filament-dashboards::dashboards.alerts.when'))->markAsRequired()->alignStart(),
                        TableColumn::make(__('filament-dashboards::dashboards.alerts.threshold'))->markAsRequired()->alignStart(),
                    ])
                    ->schema([
                        Select::make('card')->options($this->alertableCards())->required(),
                        Select::make('operator')
                            ->options(collect(AlertOperator::cases())->mapWithKeys(fn (AlertOperator $operator): array => [$operator->value => $operator->getLabel()])->all())
                            ->default(AlertOperator::Above->value)
                            ->selectablePlaceholder(false)
                            ->required(),
                        TextInput::make('threshold')->numeric()->required(),
                    ]),
            ])
            ->action(function (array $data) use ($mine): void {
                $cards = $this->alertableCards();
                $mine()->delete();

                foreach (Arr::array($data, 'alerts') as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $card = is_string($row['card'] ?? null) ? $row['card'] : '';
                    $operator = AlertOperator::tryFrom(is_string($row['operator'] ?? null) ? $row['operator'] : '');
                    $threshold = $row['threshold'] ?? null;

                    if (! array_key_exists($card, $cards) || $operator === null || ! is_numeric($threshold)) {
                        continue;
                    }

                    [$widget, $key] = explode(':', $card, 2);
                    $alert = new DashboardAlert([
                        'panel' => $this->getDashboard()->panel,
                        'dashboard_id' => $this->getDashboard()->getKey(),
                        'builder_widget_id' => (int) $widget,
                        'card_key' => $key,
                        'operator' => $operator,
                        'threshold' => $threshold + 0,
                    ]);
                    $alert->user()->associate(DashboardResource::user());
                    $alert->tenant()->associate(Filament::getTenant());
                    $alert->save();
                }
            })
            ->successNotificationTitle(__('filament-dashboards::dashboards.notifications.saved')), 'dashboard.alerts');
    }

    /**
     * @return array<string, string>
     */
    protected function alertableCards(): array
    {
        $options = [];
        $widgets = BuilderWidget::query()
            ->inScope($this->getDashboard()->panel, Filament::getTenant())
            ->visibleTo(DashboardResource::user())
            ->where('type', BuilderWidgetType::Stats)
            ->whereIn('id', $this->getDashboard()->items()->whereNotNull('builder_widget_id')->select('builder_widget_id'))
            ->get();

        foreach ($widgets as $widget) {
            foreach ($widget->keyedCards() as $key => $card) {
                $label = is_string($card['label'] ?? null) && filled($card['label']) ? $card['label'] : (is_string($card['source'] ?? null) ? $card['source'] : '');
                $options["{$widget->id}:{$key}"] = "{$widget->name} · {$label}";
            }
        }

        return $options;
    }

    public function widgetHistoryAction(): Action
    {
        return FilamentDashboardsPlugin::get()->modifyAction(WidgetHistoryAction::make('widgetHistory')
            ->record(fn (array $arguments): BuilderWidget => $this->editableWidget($arguments))
            ->after(fn () => $this->reloadDashboard()), 'dashboard.widgetHistory');
    }

    /**
     * @param  array<mixed>  $arguments
     */
    protected function editableWidget(array $arguments): BuilderWidget
    {
        $widget = BuilderWidget::query()
            ->inScope($this->getDashboard()->panel, Filament::getTenant())
            ->find($arguments['widget'] ?? null);

        abort_unless($widget instanceof BuilderWidget && $widget->canBeEditedBy(DashboardResource::user()), 403);

        return $widget;
    }

    protected function shareAction(): ShareAction
    {
        return FilamentDashboardsPlugin::get()->modifyAction(FilamentDashboardsPlugin::get()->modifyShareAction(ShareAction::make()->record($this->getDashboard())->color('gray')), 'dashboard.share');
    }

    protected function editLayoutAction(): Action
    {
        return FilamentDashboardsPlugin::get()->modifyAction(Action::make('edit')
            ->label(__('filament-dashboards::dashboards.actions.edit'))
            ->color('primary')
            ->visible($this->canEdit() && ! $this->isEditingLayout)
            ->action(function (): void {
                $this->isEditingLayout = true;

                $this->reloadDashboard();
            }), 'dashboard.edit');
    }

    protected function doneEditingAction(): Action
    {
        return FilamentDashboardsPlugin::get()->modifyAction(Action::make('doneEditing')
            ->label(__('filament-dashboards::dashboards.actions.done_editing'))
            ->color('gray')
            ->action(function (): void {
                $this->isEditingLayout = false;

                $this->reloadDashboard();
            }), 'dashboard.doneEditing');
    }

    protected function settingsAction(): EditAction
    {
        return FilamentDashboardsPlugin::get()->modifyAction(FilamentDashboardsPlugin::get()->modifySettingsAction(DashboardSettingsAction::make()
            ->record($this->getDashboard())
            ->successRedirectUrl(fn (Dashboard $record): string => DashboardResource::getUrl('view', ['record' => $record]))), 'dashboard.settings');
    }

    protected function cloneAction(): Action
    {
        return FilamentDashboardsPlugin::get()->modifyAction(DuplicateDashboardAction::make()->record($this->getDashboard()), 'dashboard.duplicate');
    }

    protected function setHomeAction(): Action
    {
        return FilamentDashboardsPlugin::get()->modifyAction(SetHomeAction::make()->record($this->getDashboard()), 'dashboard.setAsHome');
    }

    protected function removeHomeAction(): Action
    {
        return FilamentDashboardsPlugin::get()->modifyAction(RemoveHomeAction::make()->record($this->getDashboard()), 'dashboard.removeHome');
    }

    protected function deleteAction(): DeleteAction
    {
        return FilamentDashboardsPlugin::get()->modifyAction(DeleteAction::make()
            ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::DELETE))
            ->record($this->getDashboard())
            ->visible($this->getDashboard()->canBeDeletedBy(DashboardResource::user()))
            ->successRedirectUrl(DashboardResource::getUrl()), 'dashboard.delete');
    }

    protected function canEdit(): bool
    {
        return $this->canEdit ??= $this->getDashboard()->canBeEditedBy(DashboardResource::user());
    }
}

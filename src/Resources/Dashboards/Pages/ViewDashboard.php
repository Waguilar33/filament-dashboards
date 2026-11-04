<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Resources\Dashboards\Pages;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Waguilar\FilamentDashboards\Actions\AddDashboardWidget;
use Waguilar\FilamentDashboards\Actions\CloneDashboard;
use Waguilar\FilamentDashboards\Actions\SaveDashboardShares;
use Waguilar\FilamentDashboards\Actions\SetHomeDashboard;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardShare;
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
            ->schema([
                Radio::make('widget')
                    ->hiddenLabel()
                    ->options(array_map(fn (string $widget): string => $widget::dashboardLabel(), $widgets))
                    ->descriptions(array_filter(array_map(fn (string $widget): ?string => $widget::dashboardDescription(), $widgets)))
                    ->required(),
            ])
            ->action(fn (array $data) => app(AddDashboardWidget::class)->handle($this->getDashboard(), $widgets[Arr::string($data, 'widget')]));
    }

    protected function shareAction(): Action
    {
        $plugin = FilamentDashboardsPlugin::get();
        $userType = DashboardResource::user()->getMorphClass();
        $roleType = $plugin->getRolesRelationship()?->getRelated()->getMorphClass();

        return Action::make('share')
            ->label(__('filament-dashboards::dashboards.actions.share'))
            ->visible($this->getDashboard()->canBeSharedBy(DashboardResource::user()))
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->modalHeading(__('filament-dashboards::dashboards.modals.share.heading'))
            ->modalDescription(__('filament-dashboards::dashboards.modals.share.description'))
            ->fillForm(fn (): array => [
                'shares' => $this->getDashboard()->shares->map(fn (DashboardShare $share): array => [
                    'shareable_type' => $share->shareable_type,
                    'shareable_id' => $share->shareable_id,
                    'level' => $share->level->value,
                ])->all(),
            ])
            ->schema([
                Repeater::make('shares')
                    ->hiddenLabel()
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addActionLabel(__('filament-dashboards::dashboards.modals.share.add'))
                    ->columns(3)
                    ->schema([
                        Select::make('shareable_type')
                            ->label(__('filament-dashboards::dashboards.fields.shareable_type'))
                            ->options($roleType === null ? [] : [
                                $userType => __('filament-dashboards::dashboards.shareable_types.user'),
                                $roleType => __('filament-dashboards::dashboards.shareable_types.role'),
                            ])
                            ->default($userType)
                            ->selectablePlaceholder(false)
                            ->live()
                            ->visible($roleType !== null)
                            ->required(),
                        Select::make('shareable_id')
                            ->label(__('filament-dashboards::dashboards.fields.shareable'))
                            ->options(fn (Get $get): array => ($get('shareable_type') ?? $userType) === $roleType
                                ? $plugin->getShareableRoles()?->pluck('name', 'id')->all() ?? []
                                : $plugin->getShareableUsers(Filament::getTenant())
                                    ->whereKeyNot(DashboardResource::user()->getKey())
                                    ->get()
                                    ->mapWithKeys(function (Model $user): array {
                                        $key = $user->getKey();

                                        return is_int($key) || is_string($key) ? [$key => Filament::getUserName($user)] : [];
                                    })
                                    ->all())
                            ->searchable()
                            ->required(),
                        ToggleButtons::make('level')
                            ->label(__('filament-dashboards::dashboards.fields.level'))
                            ->options([
                                ShareLevel::Read->value => __('filament-dashboards::dashboards.share_levels.read'),
                                ShareLevel::Edit->value => __('filament-dashboards::dashboards.share_levels.edit'),
                            ])
                            ->default(ShareLevel::Read->value)
                            ->inline()
                            ->required(),
                    ]),
            ])
            ->action(function (array $data) use ($userType): void {
                $shares = [];

                foreach (Arr::array($data, 'shares') as $share) {
                    $id = is_array($share) ? ($share['shareable_id'] ?? null) : null;

                    if (! is_array($share) || (! is_int($id) && ! is_string($id))) {
                        continue;
                    }

                    $shares[] = [
                        'shareable_type' => Arr::string($share + ['shareable_type' => $userType], 'shareable_type'),
                        'shareable_id' => $id,
                        'level' => Arr::string($share, 'level'),
                    ];
                }

                app(SaveDashboardShares::class)->handle($this->getDashboard(), $shares);
            });
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

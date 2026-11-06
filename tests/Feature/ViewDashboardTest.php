<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Renderless;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Waguilar\FilamentDashboards\Enums\NavigationPosition;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Filament\Actions\ShareAction;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardHome;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Workbench\App\Models\User;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

function viewAs(User $user, Dashboard $dashboard): mixed
{
    test()->actingAs($user);

    return Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug]);
}

it('shows the dashboard widgets in order', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 1, 'w' => 6, 'h' => 4]);
    $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]);

    viewAs($owner, $dashboard)
        ->assertOk()
        ->assertSeeInOrder(['dashboard-item-2', 'dashboard-item-1']);
});

it('shows the empty state when the dashboard has no widgets', function (): void {
    $owner = user();

    viewAs($owner, dashboard($owner))->assertSee('No widgets yet');
});

it('refuses a dashboard the user cannot see', function (): void {
    $dashboard = dashboard(user());

    test()->actingAs(user())->get("/demo/custom-dashboards/{$dashboard->slug}")->assertNotFound();
});

it('gives the owner every action', function (): void {
    $owner = user();

    viewAs($owner, dashboard($owner))
        ->assertActionVisible('addWidget')
        ->assertActionVisible('share')
        ->assertActionVisible('edit')
        ->assertActionVisible('settings')
        ->assertActionVisible('duplicate')
        ->assertActionVisible('setAsHome')
        ->assertActionVisible('delete');
});

it('lets an edit share change the dashboard but not share or delete it', function (): void {
    $dashboard = dashboard(user());
    $editor = user();
    share($dashboard, $editor, ShareLevel::Edit);

    viewAs($editor, $dashboard)
        ->assertActionVisible('addWidget')
        ->assertActionVisible('settings')
        ->assertActionHidden('share')
        ->assertActionHidden('delete');
});

it('gives a read share and a system dashboard only clone and set as home', function (Closure $make): void {
    $reader = user();
    $dashboard = $make($reader);

    viewAs($reader, $dashboard)
        ->assertActionHidden('addWidget')
        ->assertActionHidden('share')
        ->assertActionHidden('edit')
        ->assertActionHidden('settings')
        ->assertActionHidden('delete')
        ->assertActionVisible('duplicate')
        ->assertActionVisible('setAsHome');
})->with([
    'read share' => [function (User $reader): Dashboard {
        $dashboard = dashboard(user());
        share($dashboard, $reader, ShareLevel::Read);

        return $dashboard;
    }],
    'system' => [fn (): Dashboard => dashboard(attributes: ['system_key' => 'sales'])],
]);

it('adds a widget at the end with its default size', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);

    viewAs($owner, $dashboard)->callAction('addWidget', data: ['widget' => 'orders-overview'])->assertHasNoActionErrors();

    expect($dashboard->items()->latest('id')->first()?->only(['widget', 'x', 'y', 'w', 'h']))
        ->toBe(['widget' => 'orders-overview', 'x' => 0, 'y' => 4, 'w' => 6, 'h' => 4]);
});

it('replaces who the dashboard is shared with', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $teammate = user();
    $role = Role::create(['name' => 'sales']);
    share($dashboard, user(), ShareLevel::Read);

    viewAs($owner, $dashboard)->callAction('share', data: ['shares' => [
        ['shareable_type' => $teammate->getMorphClass(), 'shareable_id' => (string) $teammate->getKey(), 'level' => 'edit'],
        ['shareable_type' => $role->getMorphClass(), 'shareable_id' => (string) $role->getKey(), 'level' => 'read'],
    ]])->assertHasNoActionErrors()->assertNotified(__('filament-dashboards::dashboards.notifications.shares_saved'));

    expect($dashboard->shares()->orderBy('id')->get()->map(fn ($share): array => [$share->shareable_type, (int) $share->shareable_id, $share->level])->all())
        ->toBe([[$teammate->getMorphClass(), $teammate->getKey(), ShareLevel::Edit], [$role->getMorphClass(), $role->getKey(), ShareLevel::Read]]);
});

it('clears who a share row names when its type changes', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $teammate = user();
    $role = Role::create(['name' => 'sales']);
    share($dashboard, $teammate, ShareLevel::Read);

    $page = viewAs($owner, $dashboard)->mountAction('share');
    $row = array_key_first($page->instance()->mountedActions[0]['data']['shares']);

    $page->set("mountedActions.0.data.shares.{$row}.shareable_type", $role->getMorphClass())
        ->assertSet("mountedActions.0.data.shares.{$row}.shareable_id", null)
        ->callMountedAction()
        ->assertHasActionErrors(["shares.{$row}.shareable_id" => 'required']);

    expect($dashboard->shares()->sole()->shareable_id)->toEqual($teammate->getKey());
});

it('fills the share form with the current shares', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $reader = user();
    share($dashboard, $reader, ShareLevel::Read);

    viewAs($owner, $dashboard)
        ->mountAction('share')
        ->assertSchemaStateSet(fn (array $state): bool => array_values($state['shares'])[0]['shareable_id'] === $reader->getKey());
});

it('clones the dashboard and opens the copy', function (): void {
    $me = user();
    $source = dashboard(attributes: ['system_key' => 'sales']);

    viewAs($me, $source)
        ->callAction('duplicate', data: ['name' => 'Mine', 'slug' => 'mine'])
        ->assertHasNoActionErrors()
        ->assertRedirect('/demo/custom-dashboards/mine');

    expect(Dashboard::query()->where('slug', 'mine')->sole()->isOwnedBy($me))->toBeTrue();
});

it('refuses a slug another dashboard in the panel already uses', function (): void {
    $me = user();
    $source = dashboard($me);
    dashboard(user(), ['slug' => 'taken']);

    viewAs($me, $source)->callAction('duplicate', data: ['name' => 'Mine', 'slug' => 'taken'])->assertHasActionErrors(['slug']);
});

it('sets the dashboard as the user home', function (): void {
    $me = user();
    $dashboard = dashboard(attributes: ['system_key' => 'sales']);

    viewAs($me, $dashboard)->callAction('setAsHome')->assertNotified();

    expect(DashboardHome::query()->sole()->dashboard_id)->toBe($dashboard->id);
});

it('hides set as home on the user home dashboard only', function (): void {
    $me = user();
    $home = dashboard($me);
    $other = dashboard($me, ['slug' => 'other']);

    viewAs($me, $home)->callAction('setAsHome')->assertActionHidden('setAsHome');
    viewAs($me, $other)->assertActionVisible('setAsHome');
});

it('removes the home from the home dashboard only', function (): void {
    $me = user();
    $home = dashboard($me);
    $other = dashboard($me, ['slug' => 'other']);

    viewAs($me, $home)->assertActionHidden('removeHome')
        ->callAction('setAsHome')
        ->assertActionVisible('removeHome');
    viewAs($me, $other)->assertActionHidden('removeHome');
    viewAs($me, $home)->callAction('removeHome')
        ->assertNotified()
        ->assertActionHidden('removeHome')
        ->assertActionVisible('setAsHome');

    expect(DashboardHome::query()->exists())->toBeFalse();
});

it('opens settings in a medium slide-over with the form in one column by default', function (): void {
    $owner = user();

    $page = viewAs($owner, dashboard($owner))->mountAction('settings')->instance();

    expect($page->getMountedAction()?->isModalSlideOver())->toBeTrue()
        ->and($page->getMountedAction()?->getModalWidth())->toBe(Width::Medium)
        ->and($page->getSchema($page->getMountedActionSchemaName())?->getColumns('lg'))->toBe(1);
});

it('lets the app modify the settings action and its form', function (): void {
    FilamentDashboardsPlugin::get()
        ->modifySettingsActionUsing(fn (EditAction $action): EditAction => $action->slideOver(false)->modalWidth(Width::TwoExtraLarge))
        ->modifyDashboardFormUsing(fn (Schema $schema): Schema => $schema->columns(2));
    $owner = user();

    $page = viewAs($owner, dashboard($owner))->mountAction('settings')->instance();

    expect($page->getMountedAction()?->isModalSlideOver())->toBeFalse()
        ->and($page->getMountedAction()?->getModalWidth())->toBe(Width::TwoExtraLarge)
        ->and($page->getSchema($page->getMountedActionSchemaName())?->getColumns('lg'))->toBe(2);
});

it('lets the app modify the share and add widget actions', function (): void {
    FilamentDashboardsPlugin::get()
        ->modifyShareActionUsing(fn (ShareAction $action): ShareAction => $action->modalWidth(Width::Medium))
        ->modifyAddWidgetActionUsing(fn (Action $action): Action => $action->slideOver(false));
    $owner = user();
    $dashboard = dashboard($owner);

    expect(viewAs($owner, $dashboard)->mountAction('share')->instance()->getMountedAction()?->getModalWidth())->toBe(Width::Medium)
        ->and(viewAs($owner, $dashboard)->mountAction('addWidget')->instance()->getMountedAction()?->isModalSlideOver())->toBeFalse();
});

it('offers the navigation groups searchable and keeps a stored group missing from them', function (): void {
    FilamentDashboardsPlugin::get()->navigationGroupsUsing(fn (): array => ['sales' => 'Ventas']);
    $owner = user();

    $page = viewAs($owner, dashboard($owner, ['show_in_navigation' => true, 'navigation_group' => 'Old']))->mountAction('settings')->instance();
    $field = $page->getSchema($page->getMountedActionSchemaName())?->getComponent(fn (mixed $component): bool => $component instanceof Select && $component->getName() === 'navigation_group', withHidden: true);

    expect($field)->toBeInstanceOf(Select::class)
        ->and($field->getOptions())->toBe(['sales' => 'Ventas', 'Old' => 'Old'])
        ->and($field->isSearchable())->toBeTrue();
});

it('lets the app replace the navigation icon field', function (): void {
    FilamentDashboardsPlugin::get()->navigationIconFieldUsing(fn (string $name): TextInput => TextInput::make($name));
    $owner = user();

    $page = viewAs($owner, dashboard($owner, ['show_in_navigation' => true]))->mountAction('settings')->instance();
    $field = $page->getSchema($page->getMountedActionSchemaName())?->getComponent(fn (mixed $component): bool => $component instanceof TextInput && $component->getName() === 'navigation_icon', withHidden: true);

    expect($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getLabel())->toBe('Navigation icon');
});

it('shows and changes the saved navigation position in settings', function (): void {
    $owner = user();
    $dashboard = dashboard($owner, ['show_in_navigation' => true, 'navigation_position' => NavigationPosition::Start]);

    viewAs($owner, $dashboard)
        ->mountAction('settings')
        ->assertSchemaStateSet(['navigation_position' => NavigationPosition::Start], 'mountedActionSchema0')
        ->setActionData(['navigation_position' => NavigationPosition::End])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($dashboard->refresh()->navigation_position)->toBe(NavigationPosition::End);
});

it('updates the settings and follows a changed slug', function (): void {
    FilamentDashboardsPlugin::get()->navigationGroupsUsing(fn (): array => ['Reports' => 'Reports']);
    $owner = user();
    $dashboard = dashboard($owner);

    viewAs($owner, $dashboard)
        ->callAction('settings', data: ['name' => 'Renamed', 'slug' => 'renamed', 'show_in_navigation' => true, 'navigation_group' => 'Reports'])
        ->assertHasNoActionErrors()
        ->assertRedirect('/demo/custom-dashboards/renamed');

    expect($dashboard->refresh()->only(['name', 'slug', 'show_in_navigation', 'navigation_group']))
        ->toBe(['name' => 'Renamed', 'slug' => 'renamed', 'show_in_navigation' => true, 'navigation_group' => 'Reports']);
});

it('deletes the dashboard', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);

    viewAs($owner, $dashboard)->callAction('delete')->assertRedirect('/demo/custom-dashboards');

    expect(Dashboard::query()->count())->toBe(0);
});

/**
 * @param  array<Action|ActionGroup>  $actions
 * @return list<string|list<string>>
 */
function headerLayout(array $actions): array
{
    return array_values(array_map(
        fn (Action|ActionGroup $action): string|array => $action instanceof ActionGroup
            ? array_values(array_map(fn (Action $grouped): string => $grouped->getName().':'.($grouped->getIcon() === null ? 'no-icon' : 'icon'), $action->getActions()))
            : $action->getName().':'.$action->getColor().':'.($action->getIcon() === null ? 'no-icon' : 'icon'),
        $actions,
    ));
}

it('lays out the header as edit, share and a menu by default, without add widget', function (): void {
    $owner = user();

    $actions = viewAs($owner, dashboard($owner))->instance()->getCachedHeaderActions();

    expect(headerLayout($actions))->toBe([
        'edit:primary:no-icon',
        'share:gray:no-icon',
        ['settings:icon', 'print:icon', 'duplicate:icon', 'setAsHome:icon', 'removeHome:icon', 'delete:icon'],
    ]);
});

it('lets the app rearrange and drop the header actions', function (): void {
    FilamentDashboardsPlugin::get()->headerActionsUsing(fn (array $actions): array => [
        $actions['share'],
        ActionGroup::make([$actions['edit'], $actions['delete']]),
    ]);
    $owner = user();

    expect(headerLayout(viewAs($owner, dashboard($owner))->instance()->getCachedHeaderActions()))->toBe([
        'share:gray:no-icon',
        ['edit:no-icon', 'delete:icon'],
    ]);
});

it('hides edit from the header while editing', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::withQueryParams(['editing' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertActionHidden('edit')
        ->assertActionVisible('share');
});

it('toggles layout editing', function (): void {
    $owner = user();

    viewAs($owner, dashboard($owner))
        ->callAction('edit')
        ->assertSet('isEditingLayout', true)
        ->assertRedirect('/demo/custom-dashboards/sales?editing=1');
});

it('shows the edit banner with finish and add widget only while editing', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertDontSee('Edit mode')
        ->assertActionVisible('addWidget')
        ->assertActionVisible('edit');

    Livewire::withQueryParams(['editing' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertSee('Edit mode')
        ->assertSee('Finish editing')
        ->assertSee('Add widget')
        ->assertActionHidden('edit')
        ->callAction(TestAction::make('doneEditing')->schemaComponent('editMode', schema: 'content'))
        ->assertSet('isEditingLayout', false)
        ->assertRedirect('/demo/custom-dashboards/sales');
});

it('frames each item with its actions while editing, and edit only for builder widgets', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $builder = builderWidget($owner, ['name' => 'Revenue']);
    $code = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2]);
    $built = $dashboard->items()->create(['widget' => $builder->dashboardKey(), 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 4]);
    $this->actingAs($owner);

    Livewire::withQueryParams(['editing' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertSeeHtml('data-editable="true"')
        ->assertSee('Orders Overview')
        ->assertSee('Revenue')
        ->assertSeeHtml("mountAction('duplicateItem', { item: {$code->id} })")
        ->assertSeeHtml("mountAction('removeItem', { item: {$code->id} })")
        ->assertSeeHtml("mountAction('editWidget', { widget: {$builder->id}, item: {$built->id} })")
        ->assertSeeHtml("mountAction('duplicateItem', { item: {$built->id} })")
        ->assertSeeHtml("mountAction('removeItem', { item: {$built->id} })");

    $built->delete();

    Livewire::withQueryParams(['editing' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertSeeHtml("mountAction('removeItem', { item: {$code->id} })")
        ->assertDontSeeHtml("mountAction('editWidget'");
});

it('shows a clean grid without frames, actions, drag or resize when reading', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2]);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertSeeHtml('data-editable="false"')
        ->assertSeeHtml('editable: false')
        ->assertDontSeeHtml('fi-dashboards-frame')
        ->assertDontSeeHtml("mountAction('removeItem', { item: {$item->id} })");
});

it('duplicates a code widget item with its size', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 3, 'options' => ['a' => 1]]);

    viewAs($owner, $dashboard)->callAction('duplicateItem', arguments: ['item' => $item->id]);

    expect($dashboard->items()->whereKeyNot($item->id)->sole()->only(['widget', 'x', 'y', 'w', 'h', 'options']))
        ->toBe(['widget' => 'orders-overview', 'x' => 0, 'y' => 3, 'w' => 4, 'h' => 3, 'options' => ['a' => 1]]);
});

it('duplicates a builder widget item as the user own copy', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $editor = user();
    share($dashboard, $editor, ShareLevel::Edit);
    $source = builderWidget($owner, ['name' => 'Revenue']);
    share($source, $editor, ShareLevel::Read);
    $item = $dashboard->items()->create(['widget' => $source->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);

    viewAs($editor, $dashboard)->callAction('duplicateItem', arguments: ['item' => $item->id]);

    $copy = BuilderWidget::query()->whereKeyNot($source->id)->sole();

    expect($copy->name)->toBe('Copy of Revenue')
        ->and($copy->isOwnedBy($editor))->toBeTrue()
        ->and($dashboard->items()->whereKeyNot($item->id)->sole()->widget)->toBe($copy->dashboardKey());
});

it('refuses to duplicate an item the editor cannot see', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $hidden = $dashboard->items()->create(['widget' => builderWidget($owner)->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);
    $editor = user();
    share($dashboard, $editor, ShareLevel::Edit);

    viewAs($editor, $dashboard)->call('mountAction', 'duplicateItem', ['item' => $hidden->id])->assertForbidden();

    expect($dashboard->items()->count())->toBe(1);
});

it('saves the layout from the grid, keeping widgets inside it', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $first = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]);
    $second = $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 2, 'w' => 6, 'h' => 4]);
    $foreign = dashboard(user(), ['slug' => 'foreign'])->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);

    viewAs($owner, $dashboard)->call('saveDashboardLayout', [
        ['id' => $first->id, 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 3],
        ['id' => $second->id, 'x' => 10, 'y' => -2, 'w' => 40, 'h' => 99],
        ['id' => $foreign->id, 'x' => 3, 'y' => 3, 'w' => 3, 'h' => 3],
        'junk',
    ]);

    expect($first->refresh()->only(['x', 'y', 'w', 'h']))->toBe(['x' => 6, 'y' => 0, 'w' => 6, 'h' => 3])
        ->and($second->refresh()->only(['x', 'y', 'w', 'h']))->toBe(['x' => 0, 'y' => 0, 'w' => 12, 'h' => 24])
        ->and($foreign->refresh()->only(['x', 'y', 'w', 'h']))->toBe(['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);
});

it('saves the stats card widths on the placement, keeping its other options', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = $dashboard->items()->create(['widget' => builderWidget($owner)->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4, 'options' => ['kept' => true]]);

    viewAs($owner, $dashboard)->call('saveCardWidths', $item->id, ['a' => 6, 'b' => 13, 'c' => 0, 'd' => '4', 'e' => 12]);

    expect($item->refresh()->options)->toBe(['kept' => true, 'card_widths' => ['a' => 6, 'e' => 12]])
        ->and($item->cardWidths())->toBe(['a' => 6, 'e' => 12]);
});

it('passes the placement card widths to stats widgets, and makes them resizable while editing', function (bool $editing): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $stats = $dashboard->items()->create(['widget' => builderWidget($owner)->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4, 'options' => ['card_widths' => ['a' => 6]]]);
    $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 4, 'w' => 12, 'h' => 2]);
    $this->actingAs($owner);

    $page = Livewire::withQueryParams(['editing' => $editing ? 1 : null])->test(ViewDashboard::class, ['record' => $dashboard->slug])->instance();
    $grid = $page->getSchema('content')?->getComponent(fn (mixed $component): bool => $component instanceof View);
    $props = array_column($grid?->getViewData()['items'] ?? [], 'props', 'id');

    expect($props[$stats->id])->toMatchArray(['cardWidths' => ['a' => 6], 'resizableCards' => $editing])
        ->and(array_filter($props, fn (array $itemProps): bool => array_key_exists('cardWidths', $itemProps)))->toHaveKeys([$stats->id])->toHaveCount(1);
})->with(['editing' => true, 'viewing' => false]);

it('removes a widget from the dashboard', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]);

    viewAs($owner, $dashboard)->callAction('removeItem', arguments: ['item' => $item->id]);

    expect($dashboard->items()->count())->toBe(0);
});

it('refuses layout changes from someone who cannot edit', function (): void {
    $dashboard = dashboard(user());
    $item = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]);
    $reader = user();
    share($dashboard, $reader, ShareLevel::Read);

    viewAs($reader, $dashboard)->call('saveDashboardLayout', [['id' => $item->id, 'x' => 3]])->assertForbidden();
    viewAs($reader, $dashboard)->call('saveCardWidths', $item->id, ['a' => 6])->assertForbidden();
    viewAs($reader, $dashboard)->assertActionHidden('removeItem')->assertActionHidden('duplicateItem');

    expect($item->refresh()->x)->toBe(0);
});

it('lays the shares out as a compact table with required, left-aligned column headers and an access select', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);

    $page = viewAs($owner, $dashboard)->mountAction('share')->instance();
    $shares = $page->getSchema($page->getMountedActionSchemaName())?->getComponent(fn (mixed $component): bool => $component instanceof Repeater && $component->getName() === 'shares');

    expect($shares?->isCompact())->toBeTrue()
        ->and(array_map(fn (TableColumn $column): bool => $column->isMarkedAsRequired(), $shares?->getTableColumns() ?? []))->toBe([true, true, true])
        ->and(array_map(fn (TableColumn $column): ?Alignment => $column->getAlignment(), $shares?->getTableColumns() ?? []))->each->toBe(Alignment::Start)
        ->and(collect($shares?->getDefaultChildComponents())->last())->toBeInstanceOf(Select::class);
});

it('offers the users the app chooses to share with', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $chosen = user();
    $other = user();
    FilamentDashboardsPlugin::get()->shareableUsersUsing(fn (): Builder => User::query()->whereKey($chosen->getKey()));

    viewAs($owner, $dashboard)
        ->callAction('share', data: ['shares' => [['shareable_type' => $other->getMorphClass(), 'shareable_id' => (string) $other->getKey(), 'level' => 'read']]])
        ->assertHasActionErrors(['shares.0.shareable_id']);
});

it('refuses a widget the user is not offered', function (): void {
    $owner = user();

    viewAs($owner, dashboard($owner))
        ->callAction('addWidget', data: ['widget' => 'hidden-widget'])
        ->assertHasActionErrors(['widget']);
});

it('leaves widgets an editor cannot see where they are', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $hidden = $dashboard->items()->create(['widget' => builderWidget($owner)->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);
    $visible = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 2]);
    $removed = $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 4, 'w' => 6, 'h' => 4]);
    $editor = user();
    share($dashboard, $editor, ShareLevel::Edit);

    viewAs($editor, $dashboard)
        ->call('saveDashboardLayout', [['id' => $visible->id, 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]])
        ->callAction('removeItem', arguments: ['item' => $removed->id]);

    expect($hidden->refresh()->only(['x', 'y', 'w', 'h']))->toBe(['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4])
        ->and($dashboard->items()->pluck('id')->sort()->values()->all())->toBe([$hidden->id, $visible->id]);
});

it('opens a shared dashboard for a viewer the app policy keeps off the dashboards list', function (): void {
    Gate::policy(Dashboard::class, NoDashboardsListPolicy::class);
    $owner = user();
    $reader = user();
    $dashboard = dashboard($owner);
    share($dashboard, $reader, ShareLevel::Read);

    $this->actingAs($reader)->get(DashboardResource::getUrl('view', ['record' => $dashboard->slug]))->assertOk();
    $this->actingAs($reader)->get(DashboardResource::getUrl('view', ['record' => dashboard($owner, ['slug' => 'private'])->slug]))->assertNotFound();
});

it('shows the dashboards list to someone with an own or shared dashboard whatever the policy says', function (): void {
    Gate::policy(Dashboard::class, NoDashboardsListPolicy::class);
    $owner = user();
    $reader = user();
    $outsider = user();
    $dashboard = dashboard($owner);
    share($dashboard, $reader, ShareLevel::Read);
    dashboard(attributes: ['system_key' => 'sales', 'slug' => 'system']);

    $this->actingAs($reader)->get(DashboardResource::getUrl())->assertOk();
    $this->actingAs($owner)->get(DashboardResource::getUrl())->assertOk();
    $this->actingAs($outsider)->get(DashboardResource::getUrl())->assertForbidden();
});

it('shows the dashboards list to someone who may create dashboards whatever the list policy says', function (): void {
    Gate::policy(Dashboard::class, CreateOnlyDashboardsPolicy::class);

    $this->actingAs(user())->get(DashboardResource::getUrl())->assertOk();
});

final class CreateOnlyDashboardsPolicy
{
    public function viewAny(): bool
    {
        return false;
    }

    public function create(): bool
    {
        return true;
    }
}

final class NoDashboardsListPolicy
{
    public function viewAny(): bool
    {
        return false;
    }
}

it('asks before removing a widget from the dashboard, then removes only that placement', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2]);

    $page = viewAs($owner, $dashboard)->mountAction(TestAction::make('removeItem')->arguments(['item' => $item->id]));
    $action = $page->instance()->getMountedAction();

    expect($action?->isConfirmationRequired())->toBeTrue()
        ->and($action?->getModalDescription())->toBe(__('filament-dashboards::dashboards.modals.remove_item.description'));

    $page->callMountedAction();

    expect($dashboard->items()->count())->toBe(0);
});

it('loads widgets lazily and keeps the grid when only positions change', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2]);
    $this->actingAs($owner);
    $grid = fn (): array => Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->instance()
        ->getSchema('content')?->getComponent(fn (mixed $component): bool => $component instanceof View, withHidden: true)?->getViewData() ?? [];

    $before = $grid();
    $item->update(['x' => 6, 'y' => 3]);

    expect($before['items'][0]['props']['lazy'])->toBeTrue()
        ->and($grid()['signature'])->toBe($before['signature'])
        ->and((new ReflectionMethod(ViewDashboard::class, 'saveDashboardLayout'))->getAttributes(Renderless::class))->toHaveCount(1);
});

it('tells editors that narrow screens do not change the layout', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::withQueryParams(['editing' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertSeeHtml('fi-dashboards-narrow-notice')
        ->assertSee(__('filament-dashboards::dashboards.edit_mode.narrow'));
});

it('prints the dashboard on letter paper with every widget loaded and the range it shows', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2]);

    $page = viewAs($owner, $dashboard)->assertSeeHtml('fi-dashboards-print-range');
    $print = collect($page->instance()->getCachedHeaderActions())
        ->flatMap(fn (mixed $action): array => $action instanceof ActionGroup ? $action->getActions() : [$action])
        ->first(fn (Action $action): bool => $action->getName() === 'print');

    expect($print?->getUrl())->toBe(DashboardResource::getUrl('view', ['record' => $dashboard, 'print' => 1]))
        ->and(file_get_contents(__DIR__.'/../../resources/dist/dashboards.css'))->toContain('size:letter');
});

it('loads every widget at once when printing', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2]);
    $this->actingAs($owner);

    $grid = Livewire::withQueryParams(['print' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])->instance()
        ->getSchema('content')?->getComponent(fn (mixed $component): bool => $component instanceof View, withHidden: true)?->getViewData() ?? [];

    expect($grid['printing'])->toBeTrue()
        ->and($grid['items'][0]['props'])->not->toHaveKey('lazy');
});

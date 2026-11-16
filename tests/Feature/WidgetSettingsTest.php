<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\CloneDashboard;
use Waguilar\FilamentDashboards\Actions\SaveWidgetSettings;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;
use Waguilar\FilamentDashboards\Pages\Home;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Workbench\App\Filament\Widgets\ChannelOrders;
use Workbench\App\Models\User;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

/**
 * @param  array<string, mixed>  $options
 */
function configurableItem(Dashboard $dashboard, array $options = []): DashboardItem
{
    return $dashboard->items()->create(['widget' => 'channel-orders', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2, 'options' => $options]);
}

/**
 * @return array<int, array<string, mixed>>
 */
function gridItems(User $user, Dashboard $dashboard): array
{
    test()->actingAs($user);

    $items = Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->instance()
        ->getSchema('content')?->getComponent(fn (mixed $component): bool => $component instanceof View, withHidden: true)?->getViewData()['items'] ?? [];

    return array_column($items, null, 'id');
}

it('offers Configure under a configurable code widget while editing', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $configurable = configurableItem($dashboard);
    $plain = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 2]);
    $built = $dashboard->items()->create(['widget' => builderWidget($owner)->dashboardKey(), 'x' => 0, 'y' => 2, 'w' => 6, 'h' => 4]);
    $this->actingAs($owner);

    Livewire::withQueryParams(['editing' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertSee('Configure')
        ->assertSeeHtml("mountAction('configureItem', { item: {$configurable->id} })")
        ->assertDontSeeHtml("mountAction('configureItem', { item: {$plain->id} })")
        ->assertDontSeeHtml("mountAction('configureItem', { item: {$built->id} })")
        ->assertSeeInOrder(["mountAction('configureItem', { item: {$configurable->id} })", "mountAction('duplicateItem', { item: {$configurable->id} })"]);

    Livewire::withQueryParams([])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertDontSeeHtml("mountAction('configureItem'");
});

it('hides Configure from someone who can only read the dashboard and refuses their call', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = configurableItem($dashboard);
    $reader = user();
    share($dashboard, $reader, ShareLevel::Read);
    $this->actingAs($reader);

    Livewire::withQueryParams(['editing' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertDontSeeHtml("mountAction('configureItem'")
        ->call('mountAction', 'configureItem', ['item' => $item->id])
        ->assertForbidden();

    expect($item->refresh()->settings())->toBe([]);
});

it('refuses to configure a widget that declares no settings', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $plain = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2]);
    $built = $dashboard->items()->create(['widget' => builderWidget($owner)->dashboardKey(), 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 4]);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->call('mountAction', 'configureItem', ['item' => $plain->id])
        ->assertForbidden();
    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->call('mountAction', 'configureItem', ['item' => $built->id])
        ->assertForbidden();
});

it('opens the widget settings in a medium slide-over filled with the saved settings', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = configurableItem($dashboard, ['settings' => ['channel' => 'store']]);
    $this->actingAs($owner);

    $page = Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->mountAction(TestAction::make('configureItem')->arguments(['item' => $item->id]))
        ->assertMountedActionModalSee('Channel Orders')
        ->assertSchemaStateSet(['channel' => 'store'], 'mountedActionSchema0');

    expect($page->instance()->getMountedAction()?->isModalSlideOver())->toBeTrue()
        ->and($page->instance()->getMountedAction()?->getModalWidth())->toBe(Width::Medium);
});

it('validates the settings with the widget form rules', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = configurableItem($dashboard);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction(TestAction::make('configureItem')->arguments(['item' => $item->id]), data: ['channel' => null])
        ->assertHasFormErrors(['channel' => 'required']);

    expect($item->refresh()->settings())->toBe([]);
});

it('saves the settings on the placement, keeping its other options, and shows them after the reload', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = configurableItem($dashboard, ['card_widths' => ['a' => 6]]);
    DemoOrderFactory::new()->count(2)->create(['channel' => 'store']);
    DemoOrderFactory::new()->create(['channel' => 'web']);
    $before = gridItems($owner, $dashboard)[$item->id]['version'];

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertSee('Orders from web: 1')
        ->callAction(TestAction::make('configureItem')->arguments(['item' => $item->id]), data: ['channel' => 'store'])
        ->assertHasNoFormErrors()
        ->assertRedirect();

    expect($item->refresh()->options)->toBe(['card_widths' => ['a' => 6], 'settings' => ['channel' => 'store']])
        ->and(gridItems($owner, $dashboard)[$item->id]['version'])->not->toBe($before);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertSee('Orders from store: 2')
        ->assertDontSee('Orders from web');
});

it('lets someone with an Edit share configure the widget', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = configurableItem($dashboard);
    $editor = user();
    share($dashboard, $editor, ShareLevel::Edit);
    $this->actingAs($editor);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction(TestAction::make('configureItem')->arguments(['item' => $item->id]), data: ['channel' => 'store'])
        ->assertHasNoFormErrors();

    expect($item->refresh()->settings())->toBe(['channel' => 'store']);
});

it('passes the placement settings to the widget wherever the dashboard shows', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = configurableItem($dashboard, ['settings' => ['channel' => 'store']]);
    $plain = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 2]);
    $items = gridItems($owner, $dashboard);

    expect($items[$item->id])->toMatchArray(['configurable' => true])
        ->and($items[$item->id]['props'])->toMatchArray(['dashboardSettings' => ['channel' => 'store']])
        ->and($items[$plain->id])->toMatchArray(['configurable' => false])
        ->and($items[$plain->id]['props'])->not->toHaveKey('dashboardSettings');

    Livewire::withQueryParams(['print' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertSee('Orders from store');

    $this->actingAs($owner)->get(Home::getUrl(['dashboard' => $dashboard->slug]))
        ->assertSee('Orders from store');
});

it('reads a single setting with a default', function (): void {
    $widget = Livewire::test(ChannelOrders::class, ['dashboardSettings' => ['channel' => 'store']])->instance();

    expect($widget->dashboardSetting('channel'))->toBe('store')
        ->and($widget->dashboardSetting('missing', 'fallback'))->toBe('fallback');
});

it('never lets the browser change the settings a widget received', function (): void {
    Livewire::test(ChannelOrders::class, ['dashboardSettings' => ['channel' => 'store']])
        ->set('dashboardSettings', ['channel' => 'web']);
})->throws(CannotUpdateLockedPropertyException::class);

it('keeps the settings when the item is duplicated or the dashboard cloned', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = configurableItem($dashboard, ['settings' => ['channel' => 'store']]);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->callAction('duplicateItem', arguments: ['item' => $item->id]);
    $clone = app(CloneDashboard::class)->handle($dashboard, user(), null, 'Copy', 'copy');

    expect($dashboard->items()->whereKeyNot($item->id)->sole()->settings())->toBe(['channel' => 'store'])
        ->and($clone->items()->get()->map(fn (DashboardItem $copy): array => $copy->settings())->all())->toBe([['channel' => 'store'], ['channel' => 'store']]);
});

it('stores settings under the settings option and replaces earlier ones', function (): void {
    $item = configurableItem(dashboard(user()), ['card_widths' => ['a' => 6], 'settings' => ['channel' => 'web', 'old' => true]]);

    app(SaveWidgetSettings::class)->handle($item, ['channel' => 'store']);

    expect($item->refresh()->options)->toBe(['card_widths' => ['a' => 6], 'settings' => ['channel' => 'store']])
        ->and($item->settings())->toBe(['channel' => 'store']);
});

it('reads no settings from an item without them', function (): void {
    expect(configurableItem(dashboard(user()))->settings())->toBe([])
        ->and(configurableItem(dashboard(user(), ['slug' => 'other']), ['settings' => 'broken'])->settings())->toBe([]);
});

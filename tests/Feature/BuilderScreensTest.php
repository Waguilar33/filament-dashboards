<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\BuildFilters;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Filament\Actions\ShareAction;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\BuilderWidgetResource;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Pages\ListBuilderWidgets;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Waguilar\FilamentDashboards\Tests\Fixtures\CentsOrdersSource;
use Waguilar\FilamentDashboards\Tests\Fixtures\GatedOrdersSource;
use Waguilar\FilamentDashboards\Widgets\BuilderChartWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderStatsWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderTableWidget;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\User;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

/**
 * @return array<string, mixed>
 */
function chartData(BuilderChartWidget $chart): array
{
    return (fn (): array => $this->getData())->call($chart);
}

function demoOrders(): void
{
    DemoOrderFactory::new()->create(['channel' => 'web', 'status' => 'paid', 'paid' => true, 'total' => 100, 'ordered_at' => now()->subDays(2), 'customer' => 'Ana']);
    DemoOrderFactory::new()->create(['channel' => 'web', 'status' => 'pending', 'paid' => false, 'total' => 50, 'ordered_at' => now()->subDays(1), 'customer' => 'Luis']);
    DemoOrderFactory::new()->create(['channel' => 'store', 'status' => 'paid', 'paid' => true, 'total' => 25, 'ordered_at' => now(), 'customer' => 'Eva']);
}

it('builds a widget and places it on the dashboard', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('buildWidget', data: [
            'name' => 'Revenue',
            'refresh_interval' => 30,
            'source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'sum', 'field' => 'total', 'period' => '30d',
        ], arguments: ['type' => 'line'])
        ->assertHasNoActionErrors();

    $widget = BuilderWidget::query()->sole();

    expect($widget->isOwnedBy($owner))->toBeTrue()
        ->and($widget->type)->toBe(BuilderWidgetType::Line)
        ->and($widget->refresh_interval)->toBe(30)
        ->and($widget->config['field'])->toBe('total')
        ->and($dashboard->items()->sole()->only(['widget', 'w']))->toBe(['widget' => "custom:{$widget->id}", 'w' => 6]);
});

it('opens the builder when a new widget type is picked', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('addWidget', data: ['widget' => 'new:pie'])
        ->assertActionMounted('buildWidget');
});

it('adds a saved widget the user can see', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $widget = builderWidget(user());
    share($widget, $owner, ShareLevel::Read);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('addWidget', data: ['widget' => $widget->dashboardKey()])
        ->assertHasNoActionErrors();

    expect($dashboard->items()->sole()->widget)->toBe($widget->dashboardKey());
});

it('edits a widget only for those allowed to edit it', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $mine = builderWidget($owner);
    $readOnly = builderWidget(user());
    share($readOnly, $owner, ShareLevel::Read);
    $this->actingAs($owner);

    $page = Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('editWidget', data: ['name' => 'Renamed', 'cards' => [['source' => 'orders', 'metric' => 'count']]], arguments: ['widget' => $mine->id])
        ->assertHasNoActionErrors();

    expect($mine->refresh()->name)->toBe('Renamed');

    $page->mountAction('editWidget', ['widget' => $readOnly->id])->assertForbidden();
});

it('shows stats cards computed for the viewer', function (): void {
    demoOrders();
    $this->actingAs(user());

    Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewConfig' => ['cards' => [
        ['source' => 'orders', 'metric' => 'count', 'label' => 'All orders'],
        ['source' => 'orders', 'metric' => 'sum', 'field' => 'total', 'label' => 'Web revenue', 'filters' => [['rules' => [['field' => 'channel', 'operator' => 'is', 'choice' => 'web']]]]],
        ['source' => 'missing', 'metric' => 'count'],
        ['source' => 'orders', 'metric' => 'sum', 'field' => 'customer'],
    ]]])
        ->assertSee('All orders')
        ->assertSee('3')
        ->assertSee('Web revenue')
        ->assertSee('150');
});

it('sizes stats cards from the widths saved on the placement', function (): void {
    demoOrders();
    $this->actingAs(user());

    $section = Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'cardWidths' => ['a' => 8, '1' => 4], 'previewConfig' => ['cards' => [
        ['key' => 'a', 'source' => 'orders', 'metric' => 'count'],
        ['source' => 'orders', 'metric' => 'count'],
        ['key' => 'c', 'source' => 'orders', 'metric' => 'count'],
    ]]])->instance()->getSectionContentComponent();

    expect($section->getColumns())->toBe(['@sm' => 12, '!@md' => 12])
        ->and(array_map(fn (Component $stat): mixed => $stat->getColumnSpan(), $section->getDefaultChildComponents()))->toBe([
            ['@sm' => 8, '!@md' => 8],
            ['@sm' => 4, '!@md' => 4],
            ['@sm' => 12, '!@md' => 12, '@xl' => 4, '!@lg' => 4],
        ]);
});

it('matches the default stats layout when no card has a saved width', function (int $count, int $span): void {
    demoOrders();
    $this->actingAs(user());

    $section = Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewConfig' => ['cards' => array_fill(0, $count, ['source' => 'orders', 'metric' => 'count'])]])
        ->instance()
        ->getSectionContentComponent();

    expect(array_map(fn (Component $stat): mixed => $stat->getColumnSpan(), $section->getDefaultChildComponents()))->each->toBe(['@sm' => 12, '!@md' => 12, '@xl' => $span, '!@lg' => $span]);
})->with([
    'two cards in thirds' => [2, 4],
    'three cards in thirds' => [3, 4],
    'four cards in quarters' => [4, 3],
]);

it('gives each new stats card its own key', function (): void {
    $owner = user();
    $this->actingAs($owner);

    $cards = Livewire::test(ViewDashboard::class, ['record' => dashboard($owner)->slug])
        ->mountAction('buildWidget', ['type' => 'stats'])
        ->instance()
        ->mountedActions[0]['data']['cards'];

    expect(array_values($cards)[0]['key'])->toBeString()->not->toBeEmpty();
});

it('shows a resize handle on each card only when the dashboard is being edited', function (bool $resizable): void {
    demoOrders();
    $this->actingAs(user());

    Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'resizableCards' => $resizable, 'previewConfig' => ['cards' => [['key' => 'a', 'source' => 'orders', 'metric' => 'count']]]])
        ->assertSeeHtml('data-card-key="a"')
        ->{$resizable ? 'assertSeeHtml' : 'assertDontSeeHtml'}('fi-dashboards-stat-resize');
})->with(['editing' => true, 'viewing' => false]);

it('shows stats cards without a widget heading unless the title is switched on', function (bool $showTitle): void {
    demoOrders();
    $owner = user();
    $this->actingAs($owner);
    $widget = builderWidget($owner, ['name' => 'Sales at a glance', 'config' => ['show_title' => $showTitle, 'cards' => [['source' => 'orders', 'metric' => 'count']]]]);

    $component = Livewire::test(BuilderStatsWidget::class, ['builderWidgetId' => $widget->id])->assertSee('Count · Orders');
    $section = $component->instance()->getSectionContentComponent();

    $component->{$showTitle ? 'assertSee' : 'assertDontSee'}('Sales at a glance');

    expect($section)->toBeInstanceOf(Section::class)
        ->and($section->isContained())->toBe($showTitle);
})->with(['title on' => true, 'title off' => false]);

it('saves the stats title switch, offered only on stats widgets', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->mountAction('buildWidget', ['type' => 'summary'])
        ->assertFormFieldHidden('show_title')
        ->unmountAction()
        ->mountAction('buildWidget', ['type' => 'stats'])
        ->assertFormFieldVisible('show_title')
        ->fillForm(['name' => 'Grouped', 'show_title' => true, 'cards' => [['source' => 'orders', 'metric' => 'count']]])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(BuilderWidget::query()->sole()->config['show_title'])->toBeTrue();
});

it('titles the stats preview with the name being typed', function (): void {
    demoOrders();
    $this->actingAs(user());

    Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewName' => 'Draft numbers', 'previewConfig' => ['show_title' => true, 'cards' => [['source' => 'orders', 'metric' => 'count']]]])
        ->assertSee('Draft numbers');
});

it('charts a breakdown with one colour per slice', function (): void {
    demoOrders();
    $this->actingAs(user());

    $data = chartData(Livewire::test(BuilderChartWidget::class, ['previewType' => 'doughnut', 'previewConfig' => ['source' => 'orders', 'group_field' => 'channel', 'metric' => 'count']])->instance());

    expect($data['labels'])->toBe(['Web', 'Store'])
        ->and($data['datasets'][0]['backgroundColor'])->toHaveCount(2);
});

it('charts a trend and a scatter', function (string $type, array $config, string $key): void {
    demoOrders();
    $this->actingAs(user());

    $data = chartData(Livewire::test(BuilderChartWidget::class, ['previewType' => $type, 'previewConfig' => $config])->instance());

    expect($data['datasets'][0]['data'])->not->toBeEmpty()
        ->and(array_key_exists($key, $data))->toBeTrue();
})->with([
    'trend by day' => ['bar', ['source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'period' => '7d'], 'labels'],
    'trend by week' => ['line', ['source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'period' => '90d'], 'labels'],
    'trend by month' => ['line', ['source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'period' => 'ytd', 'grouping' => 'day'], 'labels'],
    'scatter' => ['scatter', ['source' => 'orders', 'x_field' => 'total', 'y_field' => 'items_count'], 'datasets'],
]);

it('charts nothing for a source or field that does not exist', function (array $config): void {
    $this->actingAs(user());

    $data = chartData(Livewire::test(BuilderChartWidget::class, ['previewType' => 'line', 'previewConfig' => $config])->instance());

    expect($data['datasets'])->toBe([]);
})->with([
    'no source' => [['source' => 'missing']],
    'bad date field' => [['source' => 'orders', 'date_field' => 'customer']],
]);

it('lists rows in a table', function (): void {
    demoOrders();
    $this->actingAs(user());

    Livewire::test(BuilderTableWidget::class, ['previewType' => 'table', 'previewConfig' => ['source' => 'orders', 'columns' => ['customer', 'status'], 'sort_field' => 'total', 'sort_direction' => 'desc', 'limit' => 2]])
        ->assertSeeInOrder(['Ana', 'Paid', 'Luis', 'Pending'])
        ->assertDontSee('Eva');
});

it('shows a saved widget only to people the widget itself is shared with', function (): void {
    $owner = user();
    $widget = builderWidget($owner, ['name' => 'Team numbers']);
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => $widget->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4]);
    $teammate = user();
    share($dashboard, $teammate, ShareLevel::Read);

    $this->actingAs($teammate);
    Livewire::test(BuilderStatsWidget::class, ['builderWidgetId' => $widget->id])->assertNotFound();
    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->assertSee('No widgets yet');

    share($widget, $teammate, ShareLevel::Read);

    Livewire::test(BuilderStatsWidget::class, ['builderWidgetId' => $widget->id])->assertOk()->assertSee('Count · Orders');
    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->assertDontSee('No widgets yet');
});

it('polls a saved widget at its refresh interval', function (): void {
    $owner = user();
    $widget = builderWidget($owner, ['refresh_interval' => 30, 'type' => BuilderWidgetType::Table, 'config' => ['source' => 'orders', 'columns' => ['customer']]]);
    $this->actingAs($owner);

    Livewire::test(BuilderTableWidget::class, ['builderWidgetId' => $widget->id])->assertSeeHtml('wire:poll.30s');
});

it('turns form filter groups into engine filters', function (): void {
    expect(app(BuildFilters::class)->handle([
        ['rules' => [['field' => 'status', 'operator' => 'is', 'choice' => 'paid'], ['field' => 'total', 'operator' => 'greater_than', 'value' => '10'], 'junk']],
        ['rules' => []],
        'junk',
    ]))->toBe([[
        ['field' => 'status', 'operator' => 'is', 'value' => 'paid'],
        ['field' => 'total', 'operator' => 'greater_than', 'value' => '10'],
    ]]);
});

it('lists saved widgets by kind and clones one', function (): void {
    $me = user();
    $mine = builderWidget($me, ['name' => 'Mine']);
    $system = builderWidget(attributes: ['name' => 'System', 'system_key' => 'system']);
    $hidden = builderWidget(user(), ['name' => 'Hidden']);
    $this->actingAs($me);

    Livewire::test(ListBuilderWidgets::class)
        ->assertCanSeeTableRecords([$mine, $system])
        ->assertCanNotSeeTableRecords([$hidden])
        ->assertTableActionHidden('edit', $system)
        ->callTableAction('duplicate', $system, data: ['name' => 'My copy'])
        ->assertHasNoTableActionErrors();

    expect(BuilderWidget::query()->where('name', 'My copy')->sole()->isOwnedBy($me))->toBeTrue();
});

it('creates a widget from the list without placing it on a dashboard', function (): void {
    $me = user();
    $dashboard = dashboard($me);
    $this->actingAs($me);

    Livewire::test(ListBuilderWidgets::class)
        ->assertActionVisible('create')
        ->callAction('create', data: ['type' => 'summary'])
        ->assertActionMounted('buildWidget')
        ->fillForm(['name' => 'Orders by channel', 'source' => 'orders', 'group_field' => 'channel', 'metrics' => [['metric' => 'count']]])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $widget = BuilderWidget::query()->sole();

    expect($widget->name)->toBe('Orders by channel')
        ->and($widget->type)->toBe(BuilderWidgetType::Summary)
        ->and($widget->isOwnedBy($me))->toBeTrue()
        ->and($widget->panel)->toBe('demo')
        ->and($dashboard->items()->count())->toBe(0);
});

it('closes the widgets list and creating widgets without any data source', function (): void {
    FilamentDashboardsPlugin::get()->dataSources([GatedOrdersSource::class]);
    $this->actingAs(user());

    Livewire::test(ListBuilderWidgets::class)->assertForbidden();

    expect(BuilderWidgetResource::canCreate())->toBeFalse();
});

it('shows edit with share, duplicate and delete in a menu by default', function (): void {
    $this->actingAs(user());

    $table = Livewire::test(ListBuilderWidgets::class)->instance()->getTable();
    $actions = $table->getRecordActions();

    expect($actions[0]->getName())->toBe('edit')
        ->and($actions[1])->toBeInstanceOf(ActionGroup::class)
        ->and(array_map(fn (Action $action): ?string => $action->getName(), $actions[1]->getActions()))->toBe(['share', 'duplicate', 'delete'])
        ->and(array_keys($table->getColumns()))->toBe(['name', 'type', 'kind']);
});

it('lets the app take over the widgets table with the plugin actions', function (): void {
    FilamentDashboardsPlugin::get()->modifyWidgetsTableUsing(fn (Table $table, array $actions): Table => $table
        ->columns([TextColumn::make('name')])
        ->recordActions([
            $actions['edit'],
            $actions['duplicate']->color('gray'),
            ActionGroup::make([$actions['share'], $actions['delete']]),
        ]));
    $me = user();
    $mine = builderWidget($me, ['name' => 'Mine']);
    $this->actingAs($me);

    $page = Livewire::test(ListBuilderWidgets::class)
        ->assertTableActionVisible('edit', $mine)
        ->assertTableActionVisible('duplicate', $mine)
        ->callTableAction('duplicate', $mine, data: ['name' => 'Copy'])
        ->assertHasNoTableActionErrors();

    expect(array_keys($page->instance()->getTable()->getColumns()))->toBe(['name'])
        ->and(BuilderWidget::query()->where('name', 'Copy')->exists())->toBeTrue();
});

it('lets a registered widget policy gate the list and creating, keeping record rules', function (): void {
    Gate::policy(BuilderWidget::class, WidgetListPolicy::class);
    $me = user();
    $mine = builderWidget($me, ['name' => 'Mine']);
    $dashboard = dashboard($me);
    $this->actingAs($me);

    WidgetListPolicy::$viewAny = false;
    Livewire::test(ListBuilderWidgets::class)->assertForbidden();
    expect(BuilderWidgetResource::shouldRegisterNavigation())->toBeFalse();

    WidgetListPolicy::$viewAny = true;
    WidgetListPolicy::$create = false;
    Livewire::test(ListBuilderWidgets::class)
        ->assertOk()
        ->assertActionHidden('create')
        ->assertTableActionVisible('edit', $mine)
        ->assertTableActionHidden('duplicate', $mine);
    expect(BuilderWidgetResource::canCreate())->toBeFalse();
    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->assertActionHidden(TestAction::make('buildWidget')->arguments(['type' => 'stats']));
    $placed = $dashboard->items()->create(['widget' => $mine->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);
    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertDontSeeHtml("mountAction('duplicateItem', { item: {$placed->id} })")
        ->call('mountAction', 'duplicateItem', ['item' => $placed->id])
        ->assertForbidden();

    WidgetListPolicy::$create = true;
    Livewire::test(ListBuilderWidgets::class)->assertActionVisible('create');
    expect(BuilderWidgetResource::canEdit($mine))->toBeTrue();
});

it('keeps the widgets list open to everyone with a source when no policy is registered', function (): void {
    $this->actingAs(user());

    expect(BuilderWidgetResource::canViewAny())->toBeTrue()
        ->and(BuilderWidgetResource::canCreate())->toBeTrue()
        ->and(BuilderWidgetResource::shouldRegisterNavigation())->toBeTrue();
});

it('refuses preview settings sent from the browser', function (string $property): void {
    $this->actingAs(user());

    Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewConfig' => ['cards' => []]])
        ->set($property, null);
})->with(['previewConfig', 'previewType', 'previewName'])->throws(CannotUpdateLockedPropertyException::class);

it('edits from a dashboard only widgets in its own panel and tenant', function (): void {
    $me = user();
    $dashboard = dashboard($me);
    $elsewhere = builderWidget($me, ['panel' => 'teams']);
    $this->actingAs($me);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->call('mountAction', 'editWidget', ['widget' => $elsewhere->id])
        ->assertForbidden();
});

final class WidgetListPolicy
{
    public static bool $viewAny = true;

    public static bool $create = true;

    public function viewAny(User $user): bool
    {
        return self::$viewAny;
    }

    public function create(User $user): bool
    {
        return self::$create;
    }
}

it('lets the app modify the widget share action', function (): void {
    FilamentDashboardsPlugin::get()->modifyShareActionUsing(fn (ShareAction $action): ShareAction => $action->modalWidth(Width::Medium));
    $me = user();
    $widget = builderWidget($me);
    $this->actingAs($me);

    $page = Livewire::test(ListBuilderWidgets::class)->mountTableAction('share', $widget)->instance();

    expect($page->getMountedAction()?->getModalWidth())->toBe(Width::Medium);
});

it('saves filters built in the form', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->mountAction('buildWidget', ['type' => 'stats'])
        ->fillForm([
            'name' => 'Paid web orders',
            'cards' => [[
                'source' => 'orders',
                'metric' => 'count',
                'label' => 'Paid web',
                'filters' => [['rules' => [
                    ['field' => 'channel', 'operator' => 'is', 'choice' => 'web'],
                    ['field' => 'paid', 'operator' => 'is', 'choice' => '1'],
                    ['field' => 'ordered_at', 'operator' => 'after', 'value' => '2026-01-01'],
                ]]],
            ]],
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $rules = BuilderWidget::query()->sole()->config['cards'][0]['filters'][0]['rules'];

    expect(array_column($rules, 'field'))->toBe(['channel', 'paid', 'ordered_at']);
});

function gateOrders(Model $allowed): void
{
    FilamentDashboardsPlugin::get()->dataSources([OrdersSource::class, GatedOrdersSource::class]);
    Gate::define('view-orders', fn (Model $user): bool => $user->is($allowed));
}

it('offers only the data sources the user may view', function (): void {
    $allowed = user();
    gateOrders($allowed);

    expect(array_keys(FilamentDashboardsPlugin::get()->getDataSourcesFor($allowed, null)))->toBe(['orders', 'gated-orders'])
        ->and(array_keys(FilamentDashboardsPlugin::get()->getDataSourcesFor(user(), null)))->toBe(['orders']);
});

it('calculates nothing from a source the viewer may not view', function (): void {
    demoOrders();
    $allowed = user();
    gateOrders($allowed);
    $config = ['cards' => [['source' => 'gated-orders', 'metric' => 'count', 'label' => 'Gated count']]];

    $this->actingAs(user());
    Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewConfig' => $config])->assertDontSee('Gated count');

    $this->actingAs($allowed);
    Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewConfig' => $config])->assertSee('Gated count');
});

it('refuses saved widgets built on a source the user may not view', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    gateOrders(user());
    $gated = builderWidget($owner, ['config' => ['cards' => [['source' => 'orders', 'metric' => 'count'], ['source' => 'gated-orders', 'metric' => 'count']]]]);
    $open = builderWidget($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('addWidget', data: ['widget' => $gated->dashboardKey()])
        ->assertHasActionErrors(['widget']);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('addWidget', data: ['widget' => $open->dashboardKey()])
        ->assertHasNoActionErrors();
});

it('hides the builder when the user may view no source', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    FilamentDashboardsPlugin::get()->dataSources([GatedOrdersSource::class]);
    Gate::define('view-orders', fn (): bool => false);
    $this->actingAs($owner);

    expect(BuilderWidgetResource::shouldRegisterNavigation())->toBeFalse();

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('addWidget', data: ['widget' => 'new:pie'])
        ->assertHasActionErrors(['widget']);
});

it('leaves widgets built on a source the viewer may not view off the dashboard', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $allowed = user();
    gateOrders($allowed);
    $widget = builderWidget($owner, ['config' => ['cards' => [['source' => 'gated-orders', 'metric' => 'count']]]]);
    $dashboard->items()->create(['widget' => $widget->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4]);
    share($dashboard, $allowed, ShareLevel::Read);
    share($widget, $allowed, ShareLevel::Read);

    $this->actingAs($owner);
    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->assertSee('No widgets yet');

    $this->actingAs($allowed);
    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->assertDontSee('No widgets yet');
});

it('formats a field value and names the field on a stats card and in a table', function (): void {
    demoOrders();
    FilamentDashboardsPlugin::get()->dataSources([CentsOrdersSource::class]);
    $this->actingAs(user());

    Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewConfig' => ['cards' => [
        ['source' => 'cents-orders', 'metric' => 'sum', 'field' => 'total'],
        ['source' => 'cents-orders', 'metric' => 'count'],
    ]]])
        ->assertSee('Sum · Total · Orders in cents')
        ->assertSee('Q17.50')
        ->assertSee('Count · Orders in cents');

    Livewire::test(BuilderTableWidget::class, ['previewType' => 'table', 'previewConfig' => ['source' => 'cents-orders', 'columns' => ['customer', 'total'], 'sort_field' => 'total', 'sort_direction' => 'desc']])
        ->assertSeeInOrder(['Ana', 'Q10.00', 'Luis', 'Q5.00']);
});

it('saves shares with a save button', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    $page = Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->mountAction('share');

    expect($page->instance()->getMountedAction()?->getModalSubmitActionLabel())->toBe('Save');
});

it('lays each stats card out in two collapsible columns with its label on top', function (): void {
    $owner = user();
    $this->actingAs($owner);

    $page = Livewire::test(ViewDashboard::class, ['record' => dashboard($owner)->slug])
        ->mountAction('buildWidget', ['type' => 'stats'])
        ->instance();

    $cards = $page->getSchema($page->getMountedActionSchemaName())?->getComponent(fn (Component $component): bool => $component instanceof Repeater && $component->getName() === 'cards');

    expect($cards?->getColumns('lg'))->toBe(2)
        ->and($cards?->isCollapsible())->toBeTrue()
        ->and($cards?->getGridColumns('lg'))->toBe(2)
        ->and(collect($cards?->getDefaultChildComponents())->first(fn (Component $field): bool => $field instanceof Repeater && $field->getName() === 'filters')?->getDefaultChildComponents()[0]->getColumns('lg'))->toBe(1)
        ->and(array_map(fn (Component $field): ?string => $field instanceof Field ? $field->getName() : null, $cards?->getDefaultChildComponents() ?? []))->toBe(['key', 'label', 'source', 'metric', 'field', 'dates_field', 'filters']);
});

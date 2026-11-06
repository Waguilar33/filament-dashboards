<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\SaveBuilderWidget;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Filament\Actions\BuildWidgetAction;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Waguilar\FilamentDashboards\Widgets\BuilderTableWidget;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

it('offers only the widget types the app allows', function (): void {
    FilamentDashboardsPlugin::get()->builderWidgetTypes([BuilderWidgetType::Stats, BuilderWidgetType::Table]);
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    expect(array_keys(BuildWidgetAction::pickerSection()['options']))->toBe(['stats', 'table']);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->call('mountAction', 'buildWidget', ['type' => 'scatter'])
        ->assertNotFound();
});

it('lets the app change the builder fields of a widget type', function (): void {
    FilamentDashboardsPlugin::get()->modifyBuilderWidgetFieldsUsing(fn (array $fields, BuilderWidgetType $type): array => $type === BuilderWidgetType::Stats
        ? array_map(fn ($field) => $field instanceof Repeater ? $field->label('KPIs') : $field, $fields)
        : $fields);
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    $page = Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->mountAction(TestAction::make('buildWidget')->arguments(['type' => 'stats']));
    $instance = $page->instance();
    $cards = $instance->getSchema($instance->getMountedActionSchemaName())?->getComponent(fn (mixed $component): bool => $component instanceof Repeater && $component->getName() === 'cards', withHidden: true);

    expect($cards?->getLabel())->toBe('KPIs');
});

it('keeps tables within the app row limit', function (): void {
    FilamentDashboardsPlugin::get()->builderLimits(['table' => 2]);
    $this->actingAs(user());
    DemoOrderFactory::new()->count(4)->create();

    $table = Livewire::test(BuilderTableWidget::class, ['previewType' => 'table', 'previewConfig' => ['source' => 'orders', 'columns' => ['customer'], 'limit' => 50]]);

    expect($table->instance()->getTableRecords()->count())->toBe(2);
});

it('drops auto-refresh when the app turns it off, and raises it to the app minimum', function (): void {
    $owner = user();
    $plugin = FilamentDashboardsPlugin::get()->minRefreshInterval(60);

    $widget = app(SaveBuilderWidget::class)->handle(SaveBuilderWidget::newFor($owner, null, 'demo'), BuilderWidgetType::Stats, ['name' => 'A', 'refresh_interval' => 10, 'cards' => []]);
    expect($widget->refresh_interval)->toBe(60);

    $plugin->minRefreshInterval(null);
    $widget = app(SaveBuilderWidget::class)->handle($widget, BuilderWidgetType::Stats, ['name' => 'A', 'refresh_interval' => 10, 'cards' => []]);
    expect($widget->refresh_interval)->toBeNull();
});

<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Schemas\BuilderWidgetForm;
use Waguilar\FilamentDashboards\Tests\Fixtures\CentsOrdersSource;
use Workbench\Database\Factories\DemoOrderFactory;

it('keeps user labels untranslated when the app translates every label', function (BuilderWidgetType $type, array $config, string $label): void {
    Filament::setCurrentPanel('demo');
    FilamentDashboardsPlugin::get()->dataSources([CentsOrdersSource::class]);
    $this->actingAs(user());
    DemoOrderFactory::new()->create(['channel' => 'web', 'total' => 1000, 'paid' => true]);
    Lang::addJsonPath(__DIR__.'/../Fixtures/lang');

    Column::configureUsing(
        fn (Column $column): Column => $column->translateLabel(),
        fn () => Component::configureUsing(
            fn (Component $component): Component => $component->translateLabel(),
            fn () => Livewire::test(BuilderWidgetForm::component($type), ['previewType' => $type->value, 'previewConfig' => $config])
                ->assertOk()
                ->assertSee($label)
                ->assertDontSee('Translated'),
        ),
    );
})->with([
    'stats' => [BuilderWidgetType::Stats, ['cards' => [['source' => 'cents-orders', 'metric' => 'count', 'label' => 'Total']]], 'Total'],
    'table' => [BuilderWidgetType::Table, ['source' => 'cents-orders', 'columns' => ['channel', 'total']], 'Channel'],
    'summary' => [BuilderWidgetType::Summary, ['source' => 'cents-orders', 'group_field' => 'channel', 'metrics' => [['metric' => 'count', 'label' => 'Total', 'percent' => true]]], 'Total'],
    'figures with a share' => [BuilderWidgetType::Figures, ['columns' => [['key' => 'a', 'label' => 'Total', 'source' => 'cents-orders', 'metric' => 'count', 'share_of' => 'a']], 'base_label' => 'All'], 'Total'],
    'line' => [BuilderWidgetType::Line, ['source' => 'cents-orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'label' => 'Total'], 'Total'],
    'doughnut' => [BuilderWidgetType::Doughnut, ['source' => 'cents-orders', 'group_field' => 'channel', 'metric' => 'count', 'label' => 'Total'], 'Total'],
    'scatter' => [BuilderWidgetType::Scatter, ['source' => 'cents-orders', 'x_field' => 'total', 'y_field' => 'items_count', 'label' => 'Total'], 'Total'],
]);

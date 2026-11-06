<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\SaveBuilderWidget;
use Waguilar\FilamentDashboards\Builder\Field;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Waguilar\FilamentDashboards\Widgets\BuilderChartWidget;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

it('prints on the paper size the app chooses', function (): void {
    FilamentDashboardsPlugin::get()->printPageSize('A4');
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2]);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->assertSeeHtml('size: A4;');
});

it('colors and configures charts the app way', function (): void {
    FilamentDashboardsPlugin::get()
        ->chartColors(['#000000', '#ffffff'])
        ->modifyChartOptionsUsing(fn (array $options, string $type): array => [...$options, 'plugins' => ['legend' => ['display' => $type !== 'pie']]]);
    $this->actingAs(user());
    DemoOrderFactory::new()->create(['channel' => 'web']);
    DemoOrderFactory::new()->create(['channel' => 'store']);
    DemoOrderFactory::new()->create(['channel' => 'phone']);

    $chart = Livewire::test(BuilderChartWidget::class, ['previewType' => 'pie', 'previewConfig' => ['source' => 'orders', 'metric' => 'count', 'group_field' => 'channel']])->instance();

    expect((fn (): array => $this->getData())->call($chart)['datasets'][0]['backgroundColor'])->toBe(['#000000', '#ffffff', '#000000'])
        ->and((fn (): ?array => $this->getOptions())->call($chart)['plugins']['legend']['display'])->toBeFalse();
});

it('shows a drop in green on fields where lower is better', function (): void {
    expect(Field::number('refunds', 'Refunds')->divideBy(100)->lowerIsBetter()->dateOnly())
        ->lowerIsBetter->toBeTrue()
        ->divisor->toBe(100)
        ->dateOnly->toBeTrue();
});

it('keeps as many widget versions as the app chooses', function (): void {
    FilamentDashboardsPlugin::get()->widgetVersionsToKeep(2);
    $owner = user();
    $widget = builderWidget($owner);

    foreach (['A', 'B', 'C', 'D'] as $name) {
        app(SaveBuilderWidget::class)->handle($widget, BuilderWidgetType::Stats, ['name' => $name, 'cards' => []], $owner);
    }

    expect($widget->versions()->pluck('name')->all())->toBe(['C', 'D']);
});

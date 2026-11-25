<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Tests\Fixtures\Renderers\ListRenderer;
use Workbench\Database\Factories\DemoOrderFactory;

it('draws builder tables on a dashboard with their header, export, bold total row and a drill-down click', function (): void {
    DemoOrderFactory::new()->create(['customer' => 'Ana', 'channel' => 'web', 'total' => 300]);
    DemoOrderFactory::new()->create(['customer' => 'Eva', 'channel' => 'web', 'total' => 450]);
    DemoOrderFactory::new()->create(['customer' => 'Luis', 'channel' => 'store', 'total' => 250]);
    $owner = user();
    $dashboard = dashboard($owner);
    $summary = builderWidget($owner, ['type' => BuilderWidgetType::Summary, 'name' => 'Sales by channel', 'config' => ['source' => 'orders', 'group_field' => 'channel', 'metrics' => [['metric' => 'sum', 'field' => 'total']], 'total_row' => true]]);
    $table = builderWidget($owner, ['type' => BuilderWidgetType::Table, 'name' => 'Latest orders', 'config' => ['source' => 'orders', 'columns' => ['customer', 'total'], 'sort_field' => 'total', 'sort_direction' => 'desc']]);
    $dashboard->items()->create(['widget' => $summary->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6]);
    $dashboard->items()->create(['widget' => $table->dashboardKey(), 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 6]);
    $this->actingAs($owner);

    $page = visit("/demo/custom-dashboards/{$dashboard->slug}")->resize(1440, 900)->waitForEvent('networkidle');
    $page->script(<<<'JS'
        async () => {
            for (let tries = 0; tries < 100; tries++) {
                if (document.querySelectorAll('.fi-ta-row').length === 6) {
                    return
                }

                await new Promise((resolve) => setTimeout(resolve, 100))
            }

            throw new Error('The tables never drew')
        }
        JS);

    $state = $page->script(<<<'JS'
        () => [...document.querySelectorAll('.fi-ta-ctn')].map((card) => ({
            heading: card.querySelector('.fi-ta-header-heading')?.textContent.trim(),
            export: card.querySelector('.fi-ta-header .fi-ta-actions')?.textContent.trim(),
            rows: [...card.querySelectorAll('.fi-ta-row')].map((row) => [...row.querySelectorAll('td')].map((cell) => cell.textContent.trim())),
            boldTotal: [...card.querySelectorAll('.fi-ta-row:last-child td .fi-font-bold')].length,
        }))
        JS);

    expect($state)->toBe([
        ['heading' => 'Sales by channel', 'export' => 'Export', 'rows' => [['Web', '750'], ['Store', '250'], ['Total', '1,000']], 'boldTotal' => 2],
        ['heading' => 'Latest orders', 'export' => 'Export', 'rows' => [['Eva', '450'], ['Ana', '300'], ['Luis', '250']], 'boldTotal' => 0],
    ]);

    $page->click('.fi-ta-row:first-child .fi-dashboards-drill-down');

    expect($page->script(<<<'JS'
        async () => {
            const summary = Livewire.all().find((component) => component.name.endsWith('builder-summary-widget'))

            for (let tries = 0; tries < 40; tries++) {
                if (summary.$wire.mountedActions.length) {
                    return summary.$wire.mountedActions.map(({ name, arguments: args }) => ({ name, args }))
                }

                await new Promise((resolve) => setTimeout(resolve, 100))
            }

            return []
        }
        JS))->toBe([['name' => 'drillDown', 'args' => ['key' => 'web', 'label' => 'Web']]]);
});

it('draws a registered renderer inside the plugin card, which fits its content', function (): void {
    FilamentDashboardsPlugin::get()->renderer(BuilderWidgetType::Table, ListRenderer::class);
    DemoOrderFactory::new()->count(8)->create();
    $owner = user();
    $dashboard = dashboard($owner);
    $table = builderWidget($owner, ['type' => BuilderWidgetType::Table, 'name' => 'Latest orders', 'config' => ['source' => 'orders', 'columns' => ['customer', 'total'], 'limit' => 8]]);
    $dashboard->items()->create(['widget' => $table->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 1]);
    $this->actingAs($owner);

    $state = visit("/demo/custom-dashboards/{$dashboard->slug}")->resize(1440, 900)->waitForEvent('networkidle')->script(<<<'JS'
        async () => {
            const layout = () => document.querySelector('.grid-stack-item').getBoundingClientRect().height

            for (let tries = 0; tries < 100; tries++) {
                const before = layout()
                await new Promise((resolve) => setTimeout(resolve, 100))

                if (document.querySelectorAll('.list-row').length === 8 && layout() === before) {
                    const card = document.querySelector('.fi-ta-ctn')
                    const item = document.querySelector('.grid-stack-item')

                    return {
                        heading: card.querySelector('.fi-ta-header-heading').textContent.trim(),
                        actions: card.querySelector('.fi-ta-header .fi-ta-actions').textContent.trim(),
                        skeleton: item.dataset.skeleton,
                        gap: item.getBoundingClientRect().bottom - card.getBoundingClientRect().bottom,
                    }
                }
            }

            throw new Error('The renderer never drew')
        }
        JS);

    expect($state)->toMatchArray(['heading' => 'Latest orders', 'actions' => 'Export', 'skeleton' => 'card'])
        ->and($state['gap'])->toBeGreaterThanOrEqual(8)->toBeLessThan(10);
});

it('gives a registered renderer the same card header as the built-in table', function (): void {
    FilamentDashboardsPlugin::get()->renderer(BuilderWidgetType::Table, ListRenderer::class);
    DemoOrderFactory::new()->count(3)->create();
    $owner = user();
    $dashboard = dashboard($owner);
    $summary = builderWidget($owner, ['type' => BuilderWidgetType::Summary, 'name' => 'Built in', 'config' => ['source' => 'orders', 'group_field' => 'channel', 'metrics' => [['metric' => 'count']]]]);
    $table = builderWidget($owner, ['type' => BuilderWidgetType::Table, 'name' => 'Renderer', 'config' => ['source' => 'orders', 'columns' => ['customer']]]);
    $dashboard->items()->create(['widget' => $summary->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6]);
    $dashboard->items()->create(['widget' => $table->dashboardKey(), 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 6]);
    $this->actingAs($owner);

    $headers = visit("/demo/custom-dashboards/{$dashboard->slug}")->resize(1440, 900)->waitForEvent('networkidle')->script(<<<'JS'
        async () => {
            for (let tries = 0; tries < 100; tries++) {
                if (document.querySelector('.fi-ta-row') && document.querySelector('.list-row')) {
                    return [...document.querySelectorAll('.fi-ta-ctn')].map((card) => {
                        const box = card.getBoundingClientRect()
                        const header = card.querySelector('.fi-ta-header').getBoundingClientRect()
                        const heading = card.querySelector('.fi-ta-header-heading')
                        const actions = card.querySelector('.fi-ta-header .fi-ta-actions').getBoundingClientRect()
                        const { fontSize, fontWeight } = getComputedStyle(heading)

                        return {
                            header: [header.top - box.top, header.height, header.width],
                            heading: [heading.getBoundingClientRect().top - box.top, fontSize, fontWeight],
                            actions: [actions.top - box.top, Math.round(box.right - actions.right), actions.height],
                        }
                    })
                }

                await new Promise((resolve) => setTimeout(resolve, 100))
            }

            throw new Error('The widgets never drew')
        }
        JS);

    expect($headers)->toHaveCount(2)
        ->and($headers[1])->toBe($headers[0]);
});

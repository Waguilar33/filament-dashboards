<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

const SIZES_COLORS = <<<'JS'
    async () => {
        for (let tries = 0; tries < 100; tries++) {
            const canvas = document.querySelector('.fi-wi-chart canvas')
            const chart = canvas && Alpine.$data(canvas.closest('[x-data]')).getChart?.()

            if (chart) {
                return chart.data.datasets.map((dataset) => dataset.backgroundColor)
            }

            await new Promise((resolve) => setTimeout(resolve, 100))
        }

        throw new Error('The chart never drew')
    }
    JS;

it('paints a coded chart on a dashboard from the panel theme, with a named color, and repaints for dark mode', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => 'sizes-chart', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 8, 'fits_content' => false]);
    $this->actingAs($owner);

    $page = visit("/demo/custom-dashboards/{$dashboard->slug}")->resize(1440, 900)->waitForEvent('networkidle');
    $page->script("() => Alpine.store('theme', 'light')");

    $light = $page->script(SIZES_COLORS);

    $page->script("() => Alpine.store('theme', 'dark')");
    $page->wait(0.3);

    expect($light)->each->toStartWith('rgb(')
        ->and(array_unique(array_slice($light, 0, 3)))->toHaveCount(3)
        ->and(array_slice($light, 0, 3))->not->toContain($light[3])
        ->and($page->script(SIZES_COLORS))->not->toBe($light);
});

it('uses the colors the panel sets on coded charts too', function (): void {
    FilamentDashboardsPlugin::get()->chartColors(['#111111', '#222222']);
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => 'sizes-chart', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 8, 'fits_content' => false]);
    $this->actingAs($owner);

    $colors = visit("/demo/custom-dashboards/{$dashboard->slug}")->resize(1440, 900)->waitForEvent('networkidle')->script(SIZES_COLORS);

    expect(array_slice($colors, 0, 3))->toBe(['rgb(17, 17, 17)', 'rgb(34, 34, 34)', 'rgb(17, 17, 17)']);
});

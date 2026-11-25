<?php

declare(strict_types=1);

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

function lightness(string $rgb): float
{
    [$red, $green, $blue] = array_map(intval(...), explode(',', trim($rgb, 'rgb() ')));

    return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
}

it('paints a coded chart from the panel theme, in order, with a named color, and repaints for dark mode', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => 'sizes-chart', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 8, 'fits_content' => false]);
    $this->actingAs($owner);

    $page = visit("/demo/custom-dashboards/{$dashboard->slug}")->resize(1440, 900)->waitForEvent('networkidle');
    $page->script("() => Alpine.store('theme', 'light')");

    [$smallest, $middle, $largest, $returned] = $light = $page->script(SIZES_COLORS);

    $page->script("() => Alpine.store('theme', 'dark')");
    $page->wait(0.3);

    expect($light)->each->toStartWith('rgb(')
        ->and(lightness($smallest))->toBeGreaterThan(lightness($middle))
        ->and(lightness($middle))->toBeGreaterThan(lightness($largest))
        ->and($returned)->not->toBeIn([$smallest, $middle, $largest])
        ->and($page->script(SIZES_COLORS))->not->toBe($light);
});

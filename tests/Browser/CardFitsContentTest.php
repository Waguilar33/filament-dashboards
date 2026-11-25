<?php

declare(strict_types=1);

use Pest\Browser\Api\AwaitableWebpage;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Workbench\Database\Factories\DemoOrderFactory;

const MARGIN = 8;

const CLIENT_DRAWN_CARD = '.fi-section:has(.growing-list-add)';

const CLIENT_DRAWN_ROWS = "document.querySelectorAll('.growing-list-row').length";

const TABLE_CARD = '.fi-ta-ctn';

function cardGap(string $card): string
{
    return sprintf(<<<'JS'
        () => {
            const card = document.querySelector(%s)
            const box = card.getBoundingClientRect()
            const contentBottom = Math.max(...[...card.children].map((child) => child.getBoundingClientRect().bottom))
            const padding = card.style.paddingBottom
            card.style.paddingBottom = ''
            const { paddingBottom, borderBottomWidth } = getComputedStyle(card)
            card.style.paddingBottom = padding
            const contentHeight = contentBottom - box.top + parseFloat(paddingBottom) + parseFloat(borderBottomWidth)

            return card.closest('.grid-stack-item').getBoundingClientRect().height - 2 * %d - contentHeight
        }
        JS, json_encode($card), MARGIN);
}

function settle(AwaitableWebpage $page, string $card, string $ready): void
{
    $page->script(sprintf(<<<'JS'
        async () => {
            const layout = () => JSON.stringify([...document.querySelectorAll('.grid-stack-item, %s')].map((element) => element.getBoundingClientRect().height))

            for (let tries = 0; tries < 100; tries++) {
                const before = layout()
                await new Promise((resolve) => setTimeout(resolve, 100))

                if (document.querySelector(%s) && (%s) && layout() === before) {
                    return
                }
            }

            throw new Error('The dashboard never settled')
        }
        JS, $card, json_encode($card), $ready));
}

function dashboardWith(string $widget): Dashboard
{
    DemoOrderFactory::new()->count(5)->create();
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => $widget, 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 1]);
    test()->actingAs($owner);

    return $dashboard;
}

function visitDashboard(Dashboard $dashboard, string $card, string $ready): AwaitableWebpage
{
    $page = visit("/demo/custom-dashboards/{$dashboard->slug}")->resize(1440, 900)->waitForEvent('networkidle');

    settle($page, $card, $ready);

    return $page;
}

it('ends a card where its content ends', function (string $widget, string $card, string $ready): void {
    $gap = visitDashboard(dashboardWith($widget), $card, $ready)->script(cardGap($card));

    expect($gap)->toBeGreaterThanOrEqual(0)->toBeLessThan(2);
})->with([
    'client-drawn rows' => ['growing-list', CLIENT_DRAWN_CARD, CLIENT_DRAWN_ROWS.' === 3'],
    'table widget' => ['latest-orders', TABLE_CARD, "document.querySelectorAll('.fi-ta-row').length === 5"],
]);

it('follows its content when the content grows after load', function (): void {
    $page = visitDashboard(dashboardWith('growing-list'), CLIENT_DRAWN_CARD, CLIENT_DRAWN_ROWS.' === 3');
    $height = "() => document.querySelector('.grid-stack-item').getBoundingClientRect().height";
    $before = $page->script($height);

    $page->click('.growing-list-add');
    settle($page, CLIENT_DRAWN_CARD, CLIENT_DRAWN_ROWS.' === 7');

    expect($page->script($height) - $before)->toBeGreaterThan(4 * 37 - 2)->toBeLessThan(4 * 37 + 2)
        ->and($page->script(cardGap(CLIENT_DRAWN_CARD)))->toBeGreaterThanOrEqual(0)->toBeLessThan(2);
});

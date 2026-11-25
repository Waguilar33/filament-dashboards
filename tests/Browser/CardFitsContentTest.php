<?php

declare(strict_types=1);

use Pest\Browser\Api\AwaitableWebpage;
use Waguilar\FilamentDashboards\Models\Dashboard;

const MARGIN = 8;

const CLIENT_DRAWN_CARD = '.fi-section:has(.growing-list-add)';

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

function settle(AwaitableWebpage $page, string $card): void
{
    $page->script(sprintf(<<<'JS'
        async () => {
            const layout = () => JSON.stringify([...document.querySelectorAll('.grid-stack-item, %s')].map((element) => element.getBoundingClientRect().height))

            for (let tries = 0; tries < 100; tries++) {
                const before = layout()
                await new Promise((resolve) => setTimeout(resolve, 100))

                if (document.querySelector(%s) && layout() === before) {
                    return
                }
            }
        }
        JS, $card, json_encode($card)));
}

function dashboardWith(string $widget): Dashboard
{
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => $widget, 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 1]);
    test()->actingAs($owner);

    return $dashboard;
}

function visitDashboard(Dashboard $dashboard, string $card): AwaitableWebpage
{
    $page = visit("/demo/custom-dashboards/{$dashboard->slug}")->resize(1440, 900)->waitForEvent('networkidle');

    settle($page, $card);

    return $page;
}

it('ends a card where its content ends', function (string $widget, string $card): void {
    $gap = visitDashboard(dashboardWith($widget), $card)->script(cardGap($card));

    expect($gap)->toBeGreaterThanOrEqual(0)->toBeLessThan(2);
})->with([
    'client-drawn rows' => ['growing-list', CLIENT_DRAWN_CARD],
    'table widget' => ['latest-orders', TABLE_CARD],
]);

it('follows its content when the content grows after load', function (): void {
    $page = visitDashboard(dashboardWith('growing-list'), CLIENT_DRAWN_CARD);
    $height = "() => document.querySelector('.grid-stack-item').getBoundingClientRect().height";
    $before = $page->script($height);

    $page->click('.growing-list-add');
    settle($page, CLIENT_DRAWN_CARD);

    expect($page->script($height) - $before)->toBeGreaterThan(4 * 37 - 2)->toBeLessThan(4 * 37 + 2)
        ->and($page->script(cardGap(CLIENT_DRAWN_CARD)))->toBeGreaterThanOrEqual(0)->toBeLessThan(2);
});

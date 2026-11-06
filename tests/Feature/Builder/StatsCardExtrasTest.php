<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\Widgets\BuilderStatsWidget;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
    $this->travelTo(CarbonImmutable::parse('2026-11-18 12:00:00', 'UTC'));
    $this->actingAs(user());
});

function statsOf(array $card, array $filters = ['preset' => 30]): array
{
    $widget = Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewConfig' => ['cards' => [['source' => 'orders', 'metric' => 'sum', 'field' => 'total', 'label' => 'Sales', 'dates_field' => 'ordered_at', ...$card]]], 'pageFilters' => $filters])->instance();
    $stat = (fn (): array => $this->getStats())->call($widget)[0];

    return ['value' => $stat->getValue(), 'description' => $stat->getDescription(), 'color' => $stat->getColor()];
}

it('pins a card to its own period and names it', function (): void {
    DemoOrderFactory::new()->create(['total' => 10, 'ordered_at' => '2026-11-02 10:00:00']);
    DemoOrderFactory::new()->create(['total' => 20, 'ordered_at' => '2026-10-30 10:00:00']);

    expect(statsOf(['period' => 'month']))->toMatchArray(['value' => '10', 'description' => 'This month'])
        ->and(statsOf([])['value'])->toBe('30');
});

it('compares with the same elapsed time of the period before', function (): void {
    DemoOrderFactory::new()->create(['total' => 150, 'ordered_at' => '2026-11-18 09:00:00']);
    DemoOrderFactory::new()->create(['total' => 100, 'ordered_at' => '2026-11-17 09:00:00']);
    DemoOrderFactory::new()->create(['total' => 999, 'ordered_at' => '2026-11-17 15:00:00']);

    expect(statsOf(['period' => 'today', 'compare' => true]))->toMatchArray(['value' => '150', 'description' => 'Today · +50.0% vs previous period', 'color' => 'success']);
});

it('shows a drop, a new figure, and nothing when both periods are empty', function (): void {
    DemoOrderFactory::new()->create(['total' => 100, 'ordered_at' => '2026-11-17 09:00:00', 'channel' => 'web']);

    expect(statsOf(['period' => 'today', 'compare' => true]))->toMatchArray(['description' => 'Today · -100.0% vs previous period', 'color' => 'danger'])
        ->and(statsOf(['period' => 'today', 'compare' => true, 'field' => 'total', 'metric' => 'count', 'filters' => [['rules' => [['field' => 'channel', 'operator' => 'is', 'choice' => 'phone']]]]])['description'])->toBe('Today');

    DemoOrderFactory::new()->create(['total' => 5, 'ordered_at' => '2026-11-18 09:00:00', 'channel' => 'phone']);
    expect(statsOf(['period' => 'today', 'compare' => true, 'metric' => 'count', 'filters' => [['rules' => [['field' => 'channel', 'operator' => 'is', 'choice' => 'phone']]]]])['description'])->toBe('Today · New vs previous period');
});

it('shows progress towards a goal only over a fixed span', function (): void {
    DemoOrderFactory::new()->create(['total' => 75, 'ordered_at' => '2026-11-10 10:00:00']);

    expect(statsOf(['period' => 'month', 'goal' => 100]))->toMatchArray(['description' => 'This month · 75% of 100', 'color' => null])
        ->and(statsOf(['period' => 'month', 'goal' => '50'])['color'])->toBe('success')
        ->and(statsOf(['goal' => 100])['description'])->toBeNull();
});

it('compares calendar periods with the same part of the one before', function (): void {
    DemoOrderFactory::new()->create(['total' => 20, 'ordered_at' => '2026-11-17 09:00:00']);
    DemoOrderFactory::new()->create(['total' => 10, 'ordered_at' => '2026-11-10 09:00:00']);
    DemoOrderFactory::new()->create(['total' => 999, 'ordered_at' => '2026-11-14 09:00:00']);

    expect(statsOf(['period' => 'week', 'compare' => true])['description'])->toBe('This week · +100.0% vs previous period');
});

it('makes the dashboard show its dates only for cards that follow them', function (): void {
    expect(builderWidget(user(), ['config' => ['cards' => [['source' => 'orders', 'metric' => 'count', 'dates_field' => 'ordered_at', 'period' => 'month']]]])->followsDashboardDates())->toBeFalse()
        ->and(builderWidget(user(), ['config' => ['cards' => [['source' => 'orders', 'metric' => 'count', 'dates_field' => 'ordered_at', 'period' => 'dashboard']]]])->followsDashboardDates())->toBeTrue();
});

it('measures the period before a range by its length in days', function (): void {
    $range = new DateRange(CarbonImmutable::parse('2026-11-01 00:00:00'), CarbonImmutable::parse('2026-11-06 23:59:59'));

    expect($range->previous()->start->toDateTimeString())->toBe('2026-10-26 00:00:00')
        ->and($range->previous()->end->toDateTimeString())->toBe('2026-10-31 23:59:59')
        ->and($range->upTo(CarbonImmutable::parse('2026-11-03 08:00:00'))->end->toDateTimeString())->toBe('2026-11-03 08:00:00')
        ->and($range->previous('month')->start->toDateTimeString())->toBe('2026-10-01 00:00:00')
        ->and((new DateRange(CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31 12:00:00')))->previous('month')->end->toDateString())->toBe('2026-02-28');
});

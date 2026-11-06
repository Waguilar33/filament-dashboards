<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Waguilar\FilamentDashboards\Actions\EvaluateStatsCards;
use Waguilar\FilamentDashboards\Actions\ResolveCardRange;
use Waguilar\FilamentDashboards\Actions\ResolveDateRange;
use Waguilar\FilamentDashboards\Builder\Enums\TimeGrouping;
use Waguilar\FilamentDashboards\Dates\DatePeriod;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
    $this->travelTo(CarbonImmutable::parse('2026-11-18 12:00:00', 'UTC'));
});

final class FiscalYear extends DatePeriod
{
    public function key(): string
    {
        return 'fiscal';
    }

    public function label(): string
    {
        return 'Fiscal year';
    }

    public function range(CarbonImmutable $now): DateRange
    {
        $start = $now->month >= 7 ? $now->setDate($now->year, 7, 1) : $now->setDate($now->year - 1, 7, 1);

        return new DateRange($start->startOfDay(), $now->endOfDay());
    }
}

function rangeText(?DateRange $range): string
{
    return $range === null ? '' : $range->start->toDateString().' - '.$range->end->toDateString();
}

it('ships calendar periods including last month and this quarter', function (): void {
    expect(rangeText(app(ResolveCardRange::class)->handle('last_month')))->toBe('2026-10-01 - 2026-10-31')
        ->and(rangeText(app(ResolveCardRange::class)->handle('quarter')))->toBe('2026-10-01 - 2026-11-18')
        ->and(app(ResolveCardRange::class)->handle('dashboard'))->toBeNull();
});

it('lets the app add its own periods for cards, charts and the date bar', function (): void {
    FilamentDashboardsPlugin::get()->datePeriods([FiscalYear::class])->datePresets([1, 30, 'month', 'fiscal']);

    expect(rangeText(app(ResolveCardRange::class)->handle('fiscal')))->toBe('2026-07-01 - 2026-11-18')
        ->and(rangeText(app(ResolveDateRange::class)->handle(['preset' => 'fiscal'])))->toBe('2026-07-01 - 2026-11-18')
        ->and(rangeText(app(ResolveDateRange::class)->handle(['preset' => 'month'])))->toBe('2026-11-01 - 2026-11-18')
        ->and(rangeText(app(ResolveDateRange::class)->handle(['preset' => 'quarter'])))->toBe('2026-10-20 - 2026-11-18');
});

it('starts weeks on the day the app chooses, everywhere', function (): void {
    FilamentDashboardsPlugin::get()->weekStartsOn(CarbonInterface::SUNDAY);

    expect(rangeText(app(ResolveCardRange::class)->handle('week')))->toBe('2026-11-15 - 2026-11-18')
        ->and(TimeGrouping::Week->start(CarbonImmutable::parse('2026-11-18'))->toDateString())->toBe('2026-11-15');
});

it('refuses period keys the date bar already uses', function (): void {
    FilamentDashboardsPlugin::get()->datePeriods([new class extends DatePeriod
    {
        public function key(): string
        {
            return 'custom';
        }

        public function label(): string
        {
            return 'Custom';
        }

        public function range(CarbonImmutable $now): DateRange
        {
            return new DateRange($now, $now);
        }
    }])->getDatePeriods();
})->throws(LogicException::class);

it('flags a card pinned to a period the app no longer offers', function (): void {
    $this->actingAs(user());

    $cards = app(EvaluateStatsCards::class)->handle(
        [['source' => 'orders', 'metric' => 'count', 'label' => 'Orders', 'dates_field' => 'ordered_at', 'period' => 'fiscal']],
        user(),
        null,
        new DateRange(CarbonImmutable::now()->startOfDay(), CarbonImmutable::now()->endOfDay()),
    );

    expect($cards[0]->needsAttention)->toBeTrue();
});

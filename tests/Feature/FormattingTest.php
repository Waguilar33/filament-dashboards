<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Waguilar\FilamentDashboards\Builder\Enums\TimeGrouping;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

it('formats numbers the plugin way by default and the app way when set', function (): void {
    $plugin = FilamentDashboardsPlugin::get();

    expect($plugin->formatNumber(1234))->toBe('1,234')
        ->and($plugin->formatNumber(1234.5))->toBe('1,234.50')
        ->and($plugin->formatNumber(12.345, 1))->toBe('12.3');

    $plugin->formatNumbersUsing(fn (int|float $value, int $decimals): string => number_format($value, $decimals, ',', '.'));

    expect($plugin->formatNumber(1234.5))->toBe('1.234,50');
});

it('formats trend labels and ranges through the app date format', function (): void {
    FilamentDashboardsPlugin::get()->formatDatesUsing(fn (CarbonInterface $date, string $context): string => "{$context}:{$date->format('d/m/Y')}");

    expect(TimeGrouping::Month->label(CarbonImmutable::parse('2026-03-01')))->toBe('month:01/03/2026');
});

<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures\Charts;

use Filament\Widgets\ChartWidget;
use RuntimeException;

final class DeliveriesLineChart extends ChartWidget
{
    protected ?string $heading = 'Deliveries';

    public function mount(): void
    {
        throw new RuntimeException('A skeleton must not mount the widget.');
    }

    public function getDescription(): string
    {
        throw new RuntimeException('A skeleton must not read the description.');
    }

    /**
     * @return array<string, string>
     */
    protected function getFilters(): array
    {
        throw new RuntimeException('A skeleton must not read the filters.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        throw new RuntimeException('A skeleton must not query data.');
    }

    protected function getType(): string
    {
        return 'line';
    }
}

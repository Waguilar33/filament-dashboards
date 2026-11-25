<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures\Charts;

use Filament\Widgets\ChartWidget;

final class PlainBarChart extends ChartWidget
{
    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        return ['datasets' => [], 'labels' => []];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

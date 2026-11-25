<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures\Charts;

use Filament\Widgets\ChartWidget;
use RuntimeException;

final class UnknownTypeChart extends ChartWidget
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
        throw new RuntimeException('Type decided at runtime.');
    }
}

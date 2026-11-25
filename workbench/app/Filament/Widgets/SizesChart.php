<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboard;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;

final class SizesChart extends ChartWidget implements DashboardWidget
{
    use InteractsWithDashboard;

    protected ?string $heading = 'Sales by size';

    public static function dashboardDescription(): string
    {
        return 'Sales by tree size, stacked, with returns in the danger color.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        return [
            'datasets' => [
                ['label' => '4 to 5 ft', 'data' => [3, 5, 4]],
                ['label' => '5 to 6 ft', 'data' => [4, 2, 6]],
                ['label' => '6 to 7 ft', 'data' => [2, 3, 1]],
                ['label' => 'Returned', 'data' => [1, 0, 1], 'color' => 'danger'],
            ],
            'labels' => ['Mon', 'Tue', 'Wed'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true]]];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboard;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;

final class SignupsChart extends ChartWidget implements DashboardWidget
{
    use InteractsWithDashboard;

    protected ?string $heading = 'Signups';

    public static function dashboardDescription(): string
    {
        return 'New signups per month over the last year.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        return [
            'datasets' => [['label' => 'Signups', 'data' => [12, 19, 15, 22, 30, 28, 35, 41, 38, 46, 52, 60]]],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

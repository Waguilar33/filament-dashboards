<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboard;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;

final class GrowingList extends Widget implements DashboardWidget
{
    use InteractsWithDashboard;

    public static function dashboardDescription(): string
    {
        return 'Rows drawn in the browser after the widget loads, like a client-side data grid.';
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/../../../resources/views/widgets/growing-list.blade.php');
    }
}

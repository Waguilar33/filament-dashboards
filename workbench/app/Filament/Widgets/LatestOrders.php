<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboard;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;
use Workbench\App\Models\DemoOrder;

final class LatestOrders extends TableWidget implements DashboardWidget
{
    use InteractsWithDashboard;

    public static function dashboardDescription(): string
    {
        return 'The latest orders, as a plain Filament table widget.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(DemoOrder::query()->latest('ordered_at'))
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('channel'),
                TextColumn::make('total')->money('USD'),
            ])
            ->paginated([5]);
    }
}

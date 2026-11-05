<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Widgets;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboard;
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboardDates;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;
use Workbench\App\Models\DemoOrder;

final class OrdersByChannelTable extends Widget implements DashboardWidget, HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithDashboard;
    use InteractsWithDashboardDates;
    use InteractsWithSchemas {
        InteractsWithSchemas::__get insteadof InteractsWithDashboardDates;
    }

    public static function dashboardDescription(): string
    {
        return 'Orders and revenue per channel in the dashboard dates, as a coded schema table.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            RepeatableEntry::make('channels')
                ->hiddenLabel()
                ->state(fn (): array => $this->rows())
                ->table([
                    TableColumn::make('Channel'),
                    TableColumn::make('Orders')->alignEnd(),
                    TableColumn::make('Revenue')->alignEnd(),
                ])
                ->schema([
                    TextEntry::make('channel'),
                    TextEntry::make('orders')->alignEnd(),
                    TextEntry::make('revenue')->alignEnd(),
                ]),
        ]);
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/../../../resources/views/widgets/schema-widget.blade.php');
    }

    /**
     * @return array<int, array{channel: string, orders: string, revenue: string}>
     */
    protected function rows(): array
    {
        $range = $this->dateRange();

        return DemoOrder::query()
            ->whereBetween('ordered_at', [$range->start, $range->end])
            ->orderBy('channel')
            ->get()
            ->groupBy('channel')
            ->map(fn (EloquentCollection $orders, string $channel): array => [
                'channel' => $channel,
                'orders' => (string) $orders->count(),
                'revenue' => number_format($orders->sum(fn (DemoOrder $order): float => (float) $order->total), 2),
            ])
            ->values()
            ->all();
    }
}

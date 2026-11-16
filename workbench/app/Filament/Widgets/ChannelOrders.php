<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Widgets;

use Filament\Forms\Components\Select;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboard;
use Waguilar\FilamentDashboards\Concerns\InteractsWithDashboardSettings;
use Waguilar\FilamentDashboards\Contracts\HasDashboardSettings;
use Workbench\App\Models\DemoOrder;

final class ChannelOrders extends Widget implements HasDashboardSettings
{
    use InteractsWithDashboard;
    use InteractsWithDashboardSettings;

    protected static bool $isLazy = false;

    public static function dashboardDescription(): string
    {
        return 'Orders from the channel picked in the widget settings.';
    }

    public static function dashboardSettingsSchema(): array
    {
        return [
            Select::make('channel')
                ->options(['web' => 'Web', 'store' => 'Store'])
                ->required(),
        ];
    }

    public function render(): View
    {
        /** @var string $channel */
        $channel = $this->dashboardSetting('channel', 'web');

        return view()->file(__DIR__.'/../../../resources/views/widgets/channel-orders.blade.php', [
            'channel' => $channel,
            'orders' => DemoOrder::query()->where('channel', $channel)->count(),
        ]);
    }
}

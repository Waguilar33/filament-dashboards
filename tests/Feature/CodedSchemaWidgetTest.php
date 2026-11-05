<?php

declare(strict_types=1);

use Livewire\Livewire;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Workbench\App\Filament\Widgets\OrdersByChannelTable;
use Workbench\Database\Factories\DemoOrderFactory;

it('renders a coded schema table widget that follows the dashboard dates', function (): void {
    $this->actingAs(user());
    DemoOrderFactory::new()->create(['channel' => 'web', 'total' => 120, 'ordered_at' => now()->subDays(3)]);
    DemoOrderFactory::new()->create(['channel' => 'store', 'total' => 80, 'ordered_at' => now()->subDays(40)]);

    Livewire::test(OrdersByChannelTable::class, ['pageFilters' => ['preset' => 15]])
        ->assertSeeInOrder(['Channel', 'Orders', 'Revenue'])
        ->assertSeeInOrder(['web', '1', '120.00'])
        ->assertDontSee('store');

    Livewire::test(OrdersByChannelTable::class, ['pageFilters' => ['preset' => 60]])
        ->assertSee('store');
});

it('offers the coded schema table widget to dashboards', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => 'orders-by-channel-table', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4]);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertDontSee('No widgets yet');
});

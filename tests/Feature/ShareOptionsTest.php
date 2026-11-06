<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Workbench\App\Models\User;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

it('finds people to share with by name, or through the app search', function (): void {
    $ana = user();
    $luis = user();
    User::query()->whereKey($ana->getKey())->update(['name' => 'Ana López', 'email' => 'ana@shop.test']);
    User::query()->whereKey($luis->getKey())->update(['name' => 'Luis Pérez', 'email' => 'luis@shop.test']);
    $plugin = FilamentDashboardsPlugin::get();

    expect($plugin->searchShareableUsers(User::query(), 'Luis')->pluck('email')->all())->toBe(['luis@shop.test']);

    $plugin->searchShareableUsersUsing(fn (Builder $query, string $search): Builder => $query->where('email', 'like', "%{$search}%"));

    expect($plugin->searchShareableUsers(User::query(), 'ana@')->pluck('email')->all())->toBe(['ana@shop.test']);
});

it('drops people and roles the app does not offer, even if sent', function (): void {
    $owner = user();
    $outsider = user();
    $sales = Role::create(['name' => 'sales']);
    $admins = Role::create(['name' => 'admins']);
    FilamentDashboardsPlugin::get()
        ->shareableUsersUsing(fn (): Builder => User::query()->whereKeyNot($outsider->getKey()))
        ->shareableRolesUsing(fn (): Builder => Role::query()->where('name', 'sales'));
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->callAction('share', data: ['shares' => [
        ['shareable_type' => $sales->getMorphClass(), 'shareable_id' => (string) $sales->getKey(), 'level' => 'read'],
    ]])->assertHasNoActionErrors();

    expect($dashboard->shares()->count())->toBe(1);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->callAction('share', data: ['shares' => [
        ['shareable_type' => $admins->getMorphClass(), 'shareable_id' => (string) $admins->getKey(), 'level' => 'read'],
        ['shareable_type' => $outsider->getMorphClass(), 'shareable_id' => (string) $outsider->getKey(), 'level' => 'read'],
    ]]);

    expect($dashboard->shares()->count())->toBe(1);
});

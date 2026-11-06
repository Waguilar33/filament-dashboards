<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Waguilar\FilamentDashboards\FilamentDashboardsServiceProvider;

function rebuildTables(): void
{
    $create = include __DIR__.'/../../database/migrations/create_filament_dashboards_tables.php.stub';
    $link = include __DIR__.'/../../database/migrations/link_filament_dashboards_items_to_widgets.php.stub';
    $create->down();
    $create->up();
    $link->up();
}

it('keys owners, shares, homes and tenants the way the app keys its users and tenants', function (): void {
    config(['filament-dashboards.key_types' => ['users' => 'uuid', 'tenants' => 'ulid']]);

    rebuildTables();

    expect(Schema::getColumnType('custom_dashboards', 'owner_id'))->toBe('varchar')
        ->and(Schema::getColumnType('custom_dashboards', 'tenant_id'))->toBe('varchar')
        ->and(Schema::getColumnType('custom_dashboard_shares', 'shareable_id'))->toBe('varchar')
        ->and(Schema::getColumnType('custom_dashboard_homes', 'user_id'))->toBe('varchar')
        ->and(Schema::getColumnType('custom_dashboard_shares', 'subject_id'))->toBe('integer');
});

it('keeps the subject of a share numeric when the app defaults to uuid morphs', function (): void {
    Schema::morphUsingUuids();

    try {
        rebuildTables();
    } finally {
        Schema::defaultMorphKeyType('int');
    }

    expect(Schema::getColumnType('custom_dashboards', 'owner_id'))->toBe('varchar')
        ->and(Schema::getColumnType('custom_dashboard_shares', 'subject_id'))->toBe('integer');
});

it('keeps integer keys by default', function (): void {
    rebuildTables();

    expect(Schema::getColumnType('custom_dashboards', 'owner_id'))->toBe('integer')
        ->and(Schema::getColumnType('custom_dashboard_homes', 'user_id'))->toBe('integer');
});

it('suggests Filament Guardian only when it is missing', function (): void {
    $published = sys_get_temp_dir().'/filament-dashboards-install-'.getmypid();
    File::ensureDirectoryExists("{$published}/migrations");
    $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($published));
    $this->app->useConfigPath($published);
    $this->app->useDatabasePath($published);
    $this->app->register(FilamentDashboardsServiceProvider::class, force: true);

    $this->artisan('filament-dashboards:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsOutputToContain('Tip: install waguilar33/filament-guardian')
        ->assertSuccessful();

    class_alias(stdClass::class, 'Waguilar\FilamentGuardian\FilamentGuardianPlugin');

    $this->artisan('filament-dashboards:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->doesntExpectOutputToContain('Tip: install waguilar33/filament-guardian')
        ->assertSuccessful();

    expect(File::exists("{$published}/filament-dashboards.php"))->toBeTrue()
        ->and(File::glob("{$published}/migrations/*_create_filament_dashboards_tables.php"))->toHaveCount(1)
        ->and(File::glob("{$published}/migrations/*_link_filament_dashboards_items_to_widgets.php"))->toHaveCount(1);
});

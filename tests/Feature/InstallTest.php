<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

function rebuildTables(): void
{
    $migration = include __DIR__.'/../../database/migrations/create_filament_dashboards_tables.php.stub';
    $migration->down();
    $migration->up();
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
    $this->beforeApplicationDestroyed(fn () => File::delete([
        config_path('filament-dashboards.php'),
        ...File::glob(database_path('migrations/*_create_filament_dashboards_tables.php')),
    ]));

    $this->artisan('filament-dashboards:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->expectsOutputToContain('Tip: install waguilar33/filament-guardian')
        ->assertSuccessful();

    class_alias(stdClass::class, 'Waguilar\FilamentGuardian\FilamentGuardianPlugin');

    $this->artisan('filament-dashboards:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'no')
        ->doesntExpectOutputToContain('Tip: install waguilar33/filament-guardian')
        ->assertSuccessful();
});

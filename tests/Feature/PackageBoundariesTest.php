<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('never references the data grid plugin from its own code', function (): void {
    $files = Finder::create()->files()->in([__DIR__.'/../../src', __DIR__.'/../../resources', __DIR__.'/../../config', __DIR__.'/../../database'])->name(['*.php', '*.js']);
    $offenders = [];

    foreach ($files as $file) {
        if (preg_match('/Waguilar\\\\{1,2}FilamentDataGrid|filament-data-grid::/i', $file->getContents()) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

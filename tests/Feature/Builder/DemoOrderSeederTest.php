<?php

declare(strict_types=1);

use Workbench\App\Models\DemoOrder;
use Workbench\Database\Seeders\DemoOrderSeeder;

it('seeds orders spread over the last twelve months', function (): void {
    $this->seed(DemoOrderSeeder::class);

    expect(DemoOrder::query()->count())->toBe(300)
        ->and(DemoOrder::query()->where('ordered_at', '<', now()->subMonths(12)->startOfDay())->count())->toBe(0)
        ->and(DemoOrder::query()->where('ordered_at', '>', now())->count())->toBe(0)
        ->and(DemoOrder::query()->distinct()->pluck('channel')->sort()->values()->all())->toBe(['phone', 'store', 'web']);
});

<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Tests\TestCase;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

pest()->extend(TestCase::class)->in('Feature');

function user(): User
{
    return UserFactory::new()->create();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function dashboard(?Model $owner = null, array $attributes = []): Dashboard
{
    $dashboard = new Dashboard(['panel' => 'demo', 'name' => 'Sales', 'slug' => 'sales', ...$attributes]);
    $dashboard->owner()->associate($owner);
    $dashboard->save();

    return $dashboard;
}

function share(Dashboard $dashboard, Model $with, ShareLevel $level): void
{
    $dashboard->shares()->create([
        'shareable_type' => $with->getMorphClass(),
        'shareable_id' => $with->getKey(),
        'level' => $level,
    ]);
}

<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\SystemDashboards\SystemDashboard;

final class FinanceDashboard extends SystemDashboard
{
    public static ?int $allowed = null;

    public function key(): string
    {
        return 'finance';
    }

    public function name(): string
    {
        return 'Finance';
    }

    public function canView(Model $user, ?Model $tenant): bool
    {
        return $user->getKey() === self::$allowed;
    }

    public function items(): array
    {
        return [];
    }
}

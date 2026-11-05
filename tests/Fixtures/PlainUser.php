<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class PlainUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class Family extends Model
{
    protected $table = 'fixture_families';

    protected $guarded = [];

    public $timestamps = false;
}

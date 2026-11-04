<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class DataSource
{
    abstract public function key(): string;

    abstract public function label(): string;

    public function group(): ?string
    {
        return null;
    }

    /**
     * @return Builder<covariant Model>
     */
    abstract public function query(Model $user, ?Model $tenant): Builder;

    /**
     * @return list<Field>
     */
    abstract public function fields(): array;
}

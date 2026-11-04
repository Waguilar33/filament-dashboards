<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Field;

final class RecordsSource extends DataSource
{
    public function key(): string
    {
        return 'records';
    }

    public function label(): string
    {
        return 'Records';
    }

    /**
     * @return Builder<Record>
     */
    public function query(Model $user, ?Model $tenant): Builder
    {
        return Record::query();
    }

    public function fields(): array
    {
        return [
            Field::text('name', 'Name'),
            Field::choice('kind', 'Kind', ['a' => 'Alpha', 'b' => 'Beta']),
            Field::number('amount', 'Amount'),
            Field::number('other', 'Other'),
            Field::date('happened_at', 'Happened at'),
        ];
    }
}

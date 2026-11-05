<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\FieldType;
use Waguilar\FilamentDashboards\Builder\Field;

class ResolveField
{
    public function handle(DataSource $source, string $name, FieldType ...$types): Field
    {
        $field = Collection::make($source->fields())
            ->first(fn (Field $field): bool => $field->name === $name && ($types === [] || in_array($field->type, $types, true)));

        if (! $field instanceof Field) {
            throw new InvalidArgumentException("[{$name}] is not an accepted field of the [{$source->key()}] data source.");
        }

        return $field;
    }
}

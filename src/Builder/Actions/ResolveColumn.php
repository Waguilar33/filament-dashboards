<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\JoinClause;
use InvalidArgumentException;
use Waguilar\FilamentDashboards\Builder\Field;

class ResolveColumn
{
    /**
     * @param  Builder<covariant Model>  $query
     */
    public function handle(Builder $query, Field $field): string
    {
        if (! str_contains($field->name, '.')) {
            return $query->qualifyColumn($field->name);
        }

        $segments = explode('.', $field->name);
        $attribute = array_pop($segments);
        $model = $query->getModel();
        $parent = $model->getTable();
        $path = [];

        foreach ($segments as $segment) {
            $relation = method_exists($model, $segment) ? $model->{$segment}() : null;

            if (! $relation instanceof BelongsTo && (! $relation instanceof HasOne || $relation->isOneOfMany())) {
                throw new InvalidArgumentException("[{$field->name}] must reach its attribute through belongs-to or has-one relationships; [{$segment}] is not one.");
            }

            $path[] = $segment;
            $related = $relation->getRelated();
            $alias = 'fd_'.implode('_', $path);

            [$first, $second] = $relation instanceof BelongsTo
                ? ["{$alias}.{$relation->getOwnerKeyName()}", "{$parent}.{$relation->getForeignKeyName()}"]
                : ["{$alias}.{$relation->getForeignKeyName()}", "{$parent}.{$relation->getLocalKeyName()}"];

            $joined = collect($query->getQuery()->joins ?? [])
                ->contains(fn (JoinClause $join): bool => $join->table === "{$related->getTable()} as {$alias}");

            if (! $joined) {
                $query->leftJoin("{$related->getTable()} as {$alias}", $first, '=', $second);
            }

            $model = $related;
            $parent = $alias;
        }

        return "{$parent}.{$attribute}";
    }
}

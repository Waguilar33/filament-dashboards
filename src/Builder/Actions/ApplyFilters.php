<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\FieldType;
use Waguilar\FilamentDashboards\Builder\Enums\Operator;
use Waguilar\FilamentDashboards\Builder\Field;

class ApplyFilters
{
    /**
     * @param  Builder<covariant Model>  $query
     * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
     * @return Builder<covariant Model>
     */
    public function handle(Builder $query, DataSource $source, array $filters): Builder
    {
        $fields = Collection::make($source->fields())->keyBy('name');

        $groups = Collection::make($filters)
            ->map(fn (array $rules): Collection => Collection::make($rules)
                ->map(fn (array $rule): ?array => $this->resolve($fields, $rule))
                ->filter()
                ->values())
            ->filter(fn (Collection $rules): bool => $rules->isNotEmpty());

        if ($groups->isEmpty()) {
            return $query;
        }

        return $query->where(fn (Builder $scoped) => $groups->each(
            fn (Collection $rules) => $scoped->orWhere(fn (Builder $group) => $rules->each(
                fn (array $rule) => $this->apply($group, $rule['field'], $rule['operator'], $rule['value']),
            )),
        ));
    }

    /**
     * @param  Collection<string, Field>  $fields
     * @param  array{field: string, operator: string, value?: mixed}  $rule
     * @return array{field: Field, operator: Operator, value: string|int|float|bool|null}|null
     */
    private function resolve(Collection $fields, array $rule): ?array
    {
        $field = $fields->get($rule['field']);
        $operator = Operator::tryFrom($rule['operator']);

        if ($field === null || $operator === null || ! in_array($operator, Operator::forType($field->type), true)) {
            return null;
        }

        if (! $operator->requiresValue()) {
            return ['field' => $field, 'operator' => $operator, 'value' => null];
        }

        $value = $this->coerce($field->type, $rule['value'] ?? null);

        if (is_int($value) || is_float($value)) {
            $value = $field->toStored($value);
        }

        return $value === null ? null : ['field' => $field, 'operator' => $operator, 'value' => $value];
    }

    private function coerce(FieldType $type, mixed $value): string|int|float|bool|null
    {
        return match ($type) {
            FieldType::Number => is_numeric($value) ? $value + 0 : null,
            FieldType::Text, FieldType::Choice => (is_string($value) || is_int($value)) && filled($value) ? (string) $value : null,
            FieldType::Date => is_string($value) && strtotime($value) !== false ? Carbon::parse($value)->toDateString() : null,
            FieldType::Boolean => filled($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
        };
    }

    /**
     * @param  Builder<covariant Model>  $group
     */
    private function apply(Builder $group, Field $field, Operator $operator, string|int|float|bool|null $value): void
    {
        $column = $group->qualifyColumn($field->name);
        $textual = $field->type === FieldType::Text || $field->type === FieldType::Choice;

        $compare = fn (Builder $query, string $symbol): Builder => $field->type === FieldType::Date && is_string($value)
            ? $query->whereDate($column, $symbol, $value)
            : $query->where($column, $symbol, $value);

        match ($operator) {
            Operator::Is => $compare($group, '='),
            Operator::IsNot => $group->where(fn (Builder $query) => $query
                ->whereNull($column)
                ->orWhere(fn (Builder $other) => $compare($other, '!='))),
            Operator::Contains => $group->whereLike($column, '%'.$value.'%'),
            Operator::GreaterThan, Operator::After => $compare($group, '>'),
            Operator::LessThan, Operator::Before => $compare($group, '<'),
            Operator::IsEmpty => $group->where(fn (Builder $query) => $textual
                ? $query->whereNull($column)->orWhere($column, '')
                : $query->whereNull($column)),
            Operator::IsNotEmpty => $textual
                ? $group->whereNotNull($column)->where($column, '!=', '')
                : $group->whereNotNull($column),
        };
    }
}

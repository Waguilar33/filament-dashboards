<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\FieldType;
use Waguilar\FilamentDashboards\Builder\Field;
use Waguilar\FilamentDashboards\Dates\DateRange;

class FetchRows
{
    public function __construct(
        protected ApplyFilters $applyFilters,
        protected ResolveColumn $resolveColumn,
    ) {}

    /**
     * @param  list<string>  $columns
     * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
     * @return list<array<string, mixed>>
     */
    public function handle(
        DataSource $source,
        Model $user,
        ?Model $tenant,
        array $columns,
        ?string $sortField,
        string $sortDirection,
        array $filters,
        int $limit = 10,
        ?string $dateField = null,
        ?DateRange $dates = null,
    ): array {
        $fields = Collection::make($source->fields())->keyBy('name');

        $selected = Collection::make($columns)
            ->unique()
            ->map(fn (string $column): ?Field => $fields->get($column))
            ->filter()
            ->values();

        if ($selected->isEmpty()) {
            return [];
        }

        $query = $this->applyFilters->handle($source->query($user, $tenant), $source, $filters, $dateField, $dates);

        $sort = $sortField === null ? null : $fields->get($sortField);

        if ($sort instanceof Field) {
            $query->reorder($this->resolveColumn->handle($query, $sort), strtolower($sortDirection) === 'desc' ? 'desc' : 'asc');
        }

        return array_values($query
            ->limit($limit)
            ->toBase()
            ->get($selected->map(fn (Field $field): string => $this->resolveColumn->handle($query, $field).' as '.$field->alias())->all())
            ->map(fn (object $row): array => (array) $row)
            ->map(fn (array $row): array => $selected
                ->mapWithKeys(fn (Field $field): array => [
                    $field->alias() => match (true) {
                        $row[$field->alias()] === null => null,
                        in_array($field->type, [FieldType::Choice, FieldType::Boolean], true) => $field->valueLabel($row[$field->alias()]),
                        $field->type === FieldType::Number && is_numeric($row[$field->alias()]) => $field->fromStored($row[$field->alias()] + 0),
                        default => $row[$field->alias()],
                    },
                ])
                ->all())
            ->all());
    }
}

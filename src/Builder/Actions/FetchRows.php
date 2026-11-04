<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\FieldType;
use Waguilar\FilamentDashboards\Builder\Field;

class FetchRows
{
    public function __construct(protected ApplyFilters $applyFilters) {}

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

        $query = $this->applyFilters->handle($source->query($user, $tenant), $source, $filters);

        if ($sortField !== null && $fields->has($sortField)) {
            $query->orderBy($query->qualifyColumn($sortField), strtolower($sortDirection) === 'desc' ? 'desc' : 'asc');
        }

        return array_values($query
            ->limit($limit)
            ->toBase()
            ->get($selected->map(fn (Field $field): string => $query->qualifyColumn($field->name))->all())
            ->map(fn (object $row): array => (array) $row)
            ->map(fn (array $row): array => $selected
                ->mapWithKeys(fn (Field $field): array => [
                    $field->name => match (true) {
                        $row[$field->name] === null => null,
                        in_array($field->type, [FieldType::Choice, FieldType::Boolean], true) => $field->valueLabel($row[$field->name]),
                        $field->type === FieldType::Number && is_numeric($row[$field->name]) => $field->fromStored($row[$field->name] + 0),
                        default => $row[$field->name],
                    },
                ])
                ->all())
            ->all());
    }
}

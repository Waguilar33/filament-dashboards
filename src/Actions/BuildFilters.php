<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

class BuildFilters
{
    /**
     * @return list<list<array{field: string, operator: string, value?: mixed}>>
     */
    public function handle(mixed $groups): array
    {
        $filters = [];

        foreach (is_array($groups) ? $groups : [] as $group) {
            $rules = [];

            foreach (is_array($group) && is_array($group['rules'] ?? null) ? $group['rules'] : [] as $rule) {
                if (! is_array($rule) || ! is_string($rule['field'] ?? null) || ! is_string($rule['operator'] ?? null)) {
                    continue;
                }

                $rules[] = ['field' => $rule['field'], 'operator' => $rule['operator'], 'value' => $rule['value'] ?? $rule['choice'] ?? null];
            }

            if ($rules !== []) {
                $filters[] = $rules;
            }
        }

        return $filters;
    }
}

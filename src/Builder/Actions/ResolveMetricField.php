<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder\Actions;

use InvalidArgumentException;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\FieldType;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Builder\Field;

class ResolveMetricField
{
    public function __construct(protected ResolveField $resolveField) {}

    public function handle(DataSource $source, Metric $metric, ?string $field): ?Field
    {
        if (! $metric->requiresNumberField()) {
            return null;
        }

        if (blank($field)) {
            throw new InvalidArgumentException("The [{$metric->value}] metric needs a number field.");
        }

        return $this->resolveField->handle($source, $field, FieldType::Number);
    }
}

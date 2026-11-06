<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Exports;

use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class WidgetExporter
{
    abstract public function key(): string;

    abstract public function label(): string;

    /**
     * @param  list<string>  $headers
     * @param  list<list<bool|float|int|string|null>>  $rows
     */
    abstract public function export(string $name, array $headers, array $rows): StreamedResponse;
}

<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Exports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter extends WidgetExporter
{
    public function __construct(
        protected string $delimiter = ',',
    ) {}

    public function key(): string
    {
        return 'csv';
    }

    public function label(): string
    {
        return __('filament-dashboards::dashboards.exports.csv');
    }

    public function export(string $name, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            // Excel only reads UTF-8 accents when the file starts with a byte order mark; a leading = + - @ in text would run as a formula.
            fwrite($output, "\u{FEFF}");
            fputcsv($output, $headers, $this->delimiter, escape: '');

            foreach ($rows as $row) {
                fputcsv($output, array_map(fn (bool|float|int|string|null $value): string => is_string($value) && ! is_numeric($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1
                    ? "'".$value
                    : (string) $value, $row), $this->delimiter, escape: '');
            }

            fclose($output);
        }, "{$name}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

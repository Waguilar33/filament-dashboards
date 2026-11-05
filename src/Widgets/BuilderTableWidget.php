<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use InvalidArgumentException;
use Waguilar\FilamentDashboards\Builder\Actions\FetchRows;
use Waguilar\FilamentDashboards\Builder\Field;
use Waguilar\FilamentDashboards\Widgets\Concerns\InteractsWithBuilderWidget;

class BuilderTableWidget extends TableWidget
{
    use InteractsWithBuilderWidget;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $config = $this->widgetConfig();
        $source = $this->source($config['source'] ?? null);
        $columns = array_values(array_filter(is_array($config['columns'] ?? null) ? $config['columns'] : [], is_string(...)));
        $fields = collect($source?->fields() ?? [])->keyBy(fn (Field $field): string => $field->name);
        $limit = $config['limit'] ?? 10;
        $interval = $this->getPollingInterval();

        return $table
            ->heading($this->builderWidget()?->name)
            ->records(function () use ($source, $config, $columns, $limit): array {
                if ($source === null) {
                    return [];
                }

                try {
                    return app(FetchRows::class)->handle(
                        $source,
                        $this->viewer(),
                        $this->tenant(),
                        $columns,
                        $this->configString($config, 'sort_field'),
                        $this->configString($config, 'sort_direction') ?? 'desc',
                        $this->filters($config),
                        is_numeric($limit) ? max(1, min(100, (int) $limit)) : 10,
                        $this->configString($config, 'dates_field'),
                        $this->dashboardDates($config),
                    );
                } catch (InvalidArgumentException) {
                    return [];
                }
            })
            ->columns(array_values(array_filter(array_map(
                fn (string $column): ?TextColumn => $fields->has($column)
                    ? TextColumn::make($column)
                        ->label($fields->get($column)?->label)
                        ->formatStateUsing(fn (mixed $state): mixed => is_int($state) || is_float($state) ? ($fields->get($column)?->format($state) ?? $state) : $state)
                    : null,
                $columns,
            ))))
            ->paginated(false)
            ->poll($interval);
    }
}

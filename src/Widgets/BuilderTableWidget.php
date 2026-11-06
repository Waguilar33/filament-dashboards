<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use InvalidArgumentException;
use Waguilar\FilamentDashboards\Builder\Actions\FetchRows;
use Waguilar\FilamentDashboards\Builder\Field;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Widgets\Concerns\ExportsData;
use Waguilar\FilamentDashboards\Widgets\Concerns\InteractsWithBuilderWidget;

class BuilderTableWidget extends TableWidget
{
    use ExportsData;
    use InteractsWithBuilderWidget;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function widgetDatesChanged(): void
    {
        $this->table = $this->table($this->makeTable());
    }

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
            ->description($this->ownRangeLabel([$config]))
            ->headerActions([$this->widgetDatesAction(), $this->exportAction()])
            ->emptyStateDescription(fn (): ?string => $this->needsAttention ? $this->needsAttentionText() : null)
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
                        is_numeric($limit) ? max(1, min(FilamentDashboardsPlugin::get()->getBuilderLimit('table'), (int) $limit)) : 10,
                        $this->configString($config, 'dates_field'),
                        $this->dashboardDates($config),
                    );
                } catch (InvalidArgumentException) {
                    $this->needsAttention = true;

                    return [];
                }
            })
            ->columns(array_values(array_filter(array_map(
                fn (string $column): ?TextColumn => ($field = $fields->get($column)) instanceof Field
                    ? TextColumn::make($field->alias())
                        ->label($field->label)
                        ->translateLabel(false)
                        ->formatStateUsing(fn (mixed $state): mixed => is_int($state) || is_float($state) ? ($field->format($state) ?? $this->formatNumber($state)) : $state)
                    : null,
                $columns,
            ))))
            ->paginated(false)
            ->poll($interval);
    }

    protected function exportSourceKeys(): array
    {
        $source = $this->configString($this->widgetConfig(), 'source');

        return $source === null ? [] : [$source];
    }
}

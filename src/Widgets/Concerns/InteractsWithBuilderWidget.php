<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets\Concerns;

use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Waguilar\FilamentDashboards\Actions\BuildFilters;
use Waguilar\FilamentDashboards\Actions\FindBuilderWidget;
use Waguilar\FilamentDashboards\Actions\ResolveCardRange;
use Waguilar\FilamentDashboards\Actions\ResolveDateRange;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Field;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;

trait InteractsWithBuilderWidget
{
    use InteractsWithPageFilters;

    #[Locked]
    public ?int $builderWidgetId = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $previewConfig = null;

    #[Locked]
    public ?string $previewType = null;

    #[Locked]
    public ?string $previewName = null;

    protected ?BuilderWidget $resolvedBuilderWidget = null;

    protected bool $needsAttention = false;

    protected function needsAttentionText(): string
    {
        return __('filament-dashboards::builder.needs_attention');
    }

    protected function builderWidget(): ?BuilderWidget
    {
        if ($this->builderWidgetId === null) {
            return null;
        }

        return $this->resolvedBuilderWidget ??= app(FindBuilderWidget::class)->handle($this->builderWidgetId, $this->viewer())
            ?? abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    protected function widgetConfig(): array
    {
        return $this->previewConfig ?? $this->builderWidget()->config ?? [];
    }

    protected function widgetType(): string
    {
        return $this->previewType ?? $this->builderWidget()?->type->value ?? 'line';
    }

    protected function viewer(): Model
    {
        /** @var Model */
        return Filament::auth()->user();
    }

    protected function tenant(): ?Model
    {
        return Filament::getTenant();
    }

    protected function source(mixed $key): ?DataSource
    {
        return is_string($key) ? (FilamentDashboardsPlugin::get()->getDataSourcesFor($this->viewer(), $this->tenant())[$key] ?? null) : null;
    }

    protected function field(DataSource $source, ?string $name): ?Field
    {
        foreach ($source->fields() as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $config
     * @return list<list<array{field: string, operator: string, value?: mixed}>>
     */
    protected function filters(array $config): array
    {
        return app(BuildFilters::class)->handle($config['filters'] ?? []);
    }

    /**
     * @param  array<mixed>  $config
     */
    protected function configString(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        return is_string($value) && filled($value) ? $value : null;
    }

    /**
     * @param  array<mixed>  $config
     */
    protected function dashboardDates(array $config): ?DateRange
    {
        if ($this->configString($config, 'dates_field') === null) {
            return null;
        }

        return app(ResolveCardRange::class)->handle($this->configString($config, 'period') ?? 'dashboard')
            ?? app(ResolveDateRange::class)->handle($this->pageFilters);
    }

    protected function getPollingInterval(): ?string
    {
        $interval = $this->builderWidget()?->refresh_interval;

        return filled($interval) ? "{$interval}s" : null;
    }

    protected function formatNumber(int|float $value): string
    {
        return FilamentDashboardsPlugin::get()->formatNumber($value);
    }
}

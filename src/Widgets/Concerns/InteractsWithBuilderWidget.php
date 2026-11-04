<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets\Concerns;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Waguilar\FilamentDashboards\Actions\BuildFilters;
use Waguilar\FilamentDashboards\Actions\FindBuilderWidget;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;

trait InteractsWithBuilderWidget
{
    #[Locked]
    public ?int $builderWidgetId = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $previewConfig = null;

    public ?string $previewType = null;

    protected ?BuilderWidget $resolvedBuilderWidget = null;

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

    protected function getPollingInterval(): ?string
    {
        $interval = $this->builderWidget()?->refresh_interval;

        return filled($interval) ? "{$interval}s" : null;
    }

    protected function formatNumber(int|float $value): string
    {
        return is_int($value) || floor($value) === $value ? number_format($value) : number_format($value, 2);
    }
}

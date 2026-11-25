<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\TableWidget;
use ReflectionClass;
use ReflectionMethod;
use Throwable;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Models\BuilderWidget;

class ResolveWidgetSkeleton
{
    /**
     * @var array<class-string, array{shape: string, heading: bool, description: bool, action: bool}>
     */
    protected static array $codeWidgets = [];

    /**
     * @param  class-string  $component
     * @return array{shape: string, heading: bool, description: bool, action: bool}
     */
    public function handle(string $component, ?BuilderWidget $builderWidget = null): array
    {
        if ($builderWidget instanceof BuilderWidget && $builderWidget->type->isChart()) {
            return $this->builderChart($builderWidget);
        }

        return static::$codeWidgets[$component] ??= $this->codeWidget($component);
    }

    /**
     * @param  class-string  $component
     * @return array{shape: string, heading: bool, description: bool, action: bool}
     */
    protected function codeWidget(string $component): array
    {
        $shape = match (true) {
            is_a($component, StatsOverviewWidget::class, true) => 'stats',
            is_a($component, ChartWidget::class, true) => $this->chartShape($this->chartType($component)),
            is_a($component, TableWidget::class, true) => 'table',
            default => 'card',
        };

        if (! is_a($component, ChartWidget::class, true)) {
            return ['shape' => $shape, 'heading' => false, 'description' => false, 'action' => false];
        }

        return [
            'shape' => $shape,
            'heading' => $this->overrides($component, 'getHeading') || $this->hasDefault($component, 'heading'),
            'description' => $this->overrides($component, 'getDescription') || $this->hasDefault($component, 'description'),
            'action' => $this->overrides($component, 'getFilters') || method_exists($component, 'getFiltersSchema'),
        ];
    }

    /**
     * @return array{shape: string, heading: bool, description: bool, action: bool}
     */
    protected function builderChart(BuilderWidget $widget): array
    {
        $config = $widget->config ?? [];
        $period = is_string($config['period'] ?? null) ? $config['period'] : null;

        return [
            'shape' => $this->chartShape($widget->type->value),
            'heading' => true,
            'description' => in_array($widget->type, [BuilderWidgetType::Line, BuilderWidgetType::Bar], true)
                ? ($period ?? '12m') !== 'dashboard'
                : filled($config['dates_field'] ?? null) && $period !== null && $period !== 'dashboard',
            'action' => (bool) ($config['viewer_dates'] ?? false) && $widget->datedPeriods() !== [],
        ];
    }

    protected function chartShape(?string $type): string
    {
        return match ($type) {
            'line' => 'line',
            'bar' => 'bar',
            'pie', 'doughnut', 'polarArea' => 'pie',
            default => 'chart',
        };
    }

    /**
     * @param  class-string  $component
     */
    protected function chartType(string $component): ?string
    {
        try {
            $type = (new ReflectionMethod($component, 'getType'))->invoke((new ReflectionClass($component))->newInstanceWithoutConstructor());
        } catch (Throwable) {
            return null;
        }

        return is_string($type) ? $type : null;
    }

    /**
     * @param  class-string  $component
     */
    protected function overrides(string $component, string $method): bool
    {
        return method_exists($component, $method) && (new ReflectionMethod($component, $method))->getDeclaringClass()->getName() !== ChartWidget::class;
    }

    /**
     * @param  class-string  $component
     */
    protected function hasDefault(string $component, string $property): bool
    {
        $class = new ReflectionClass($component);

        return $class->hasProperty($property) && filled($class->getProperty($property)->getDefaultValue());
    }
}

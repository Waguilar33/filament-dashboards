<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Enums;

enum BuilderWidgetType: string
{
    case Stats = 'stats';
    case Table = 'table';
    case Line = 'line';
    case Bar = 'bar';
    case Scatter = 'scatter';
    case Pie = 'pie';
    case Doughnut = 'doughnut';
    case PolarArea = 'polarArea';

    public function getLabel(): string
    {
        return __("filament-dashboards::dashboards.widget_types.{$this->value}.label");
    }

    public function getDescription(): string
    {
        return __("filament-dashboards::dashboards.widget_types.{$this->value}.description");
    }

    public function preview(): string
    {
        return match ($this) {
            self::PolarArea => 'polar',
            default => $this->value,
        };
    }

    public function isChart(): bool
    {
        return ! in_array($this, [self::Stats, self::Table], true);
    }

    /**
     * @return array{w: int, h: int}
     */
    public function defaultSize(): array
    {
        return match ($this) {
            self::Stats, self::Table => ['w' => 12, 'h' => 4],
            default => ['w' => 6, 'h' => 8],
        };
    }
}

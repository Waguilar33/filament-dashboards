<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets\Concerns;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Waguilar\FilamentDashboards\Actions\ApplyWidgetDates;
use Waguilar\FilamentDashboards\Actions\BuildFilters;
use Waguilar\FilamentDashboards\Actions\FindBuilderWidget;
use Waguilar\FilamentDashboards\Actions\ResolveDateRange;
use Waguilar\FilamentDashboards\Actions\ResolveWidgetRange;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Field;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\FilamentDashboardsIcon;
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

    #[Locked]
    public ?int $dashboardItemId = null;

    #[Locked]
    public bool $offersDashboardDates = false;

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
        if ($this->previewConfig !== null) {
            return $this->previewConfig;
        }

        $config = $this->builderWidget()->config ?? [];
        $dates = $this->widgetDates();

        return $dates === null ? $config : app(ApplyWidgetDates::class)->handle($config, $dates);
    }

    protected function offersWidgetDates(): bool
    {
        return $this->dashboardItemId !== null && $this->builderWidget()?->datedPeriods() !== [];
    }

    /**
     * @return array{preset: int|string, startDate?: string, endDate?: string}|null
     */
    protected function widgetDates(): ?array
    {
        return $this->dashboardItemId === null ? null : $this->validWidgetDates(session()->get($this->widgetDatesKey()));
    }

    protected function widgetDatesKey(): string
    {
        return "filament-dashboards.widget-dates.{$this->dashboardItemId}";
    }

    /**
     * @return array<int|string, string>
     */
    protected function widgetDatesOptions(): array
    {
        $plugin = FilamentDashboardsPlugin::get();
        $ownDefault = array_diff($this->builderWidget()?->datedPeriods() ?? [], [ApplyWidgetDates::DASHBOARD]) !== [];
        $options = ['default' => __($ownDefault ? 'filament-dashboards::dashboards.dates.widget_default' : 'filament-dashboards::dashboards.dates.dashboard')];

        if ($ownDefault && $this->offersDashboardDates) {
            $options[ApplyWidgetDates::DASHBOARD] = __('filament-dashboards::dashboards.dates.dashboard');
        }

        foreach ($plugin->getWidgetDatePresets() as $preset) {
            $options[$preset] = $plugin->getDatePresetLabel($preset);
        }

        $options[ResolveDateRange::CUSTOM] = __('filament-dashboards::dashboards.dates.custom');

        return $options;
    }

    /**
     * @return array{preset: int|string, startDate?: string, endDate?: string}|null
     */
    protected function validWidgetDates(mixed $dates): ?array
    {
        if (! is_array($dates)) {
            return null;
        }

        $preset = $dates['preset'] ?? null;
        $preset = is_numeric($preset) ? (int) $preset : $preset;

        if (! is_int($preset) && ! is_string($preset)) {
            return null;
        }

        if (! array_key_exists($preset, $this->widgetDatesOptions()) || $preset === 'default') {
            return null;
        }

        if ($preset !== ResolveDateRange::CUSTOM) {
            return ['preset' => $preset];
        }

        $start = $dates['startDate'] ?? null;
        $end = $dates['endDate'] ?? null;

        if (! is_string($start) || ! is_string($end) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1) {
            return null;
        }

        return ['preset' => $preset, 'startDate' => $start, 'endDate' => $end];
    }

    /**
     * @param  array<mixed>  $data
     */
    protected function keepWidgetDates(array $data): void
    {
        $dates = $this->validWidgetDates($data);

        if ($dates === null) {
            session()->forget($this->widgetDatesKey());

            return;
        }

        session()->put($this->widgetDatesKey(), $dates);
    }

    /**
     * @return array{preset: int|string, startDate: ?string, endDate: ?string}
     */
    protected function widgetDatesState(): array
    {
        $dates = $this->widgetDates();

        return [
            'preset' => $dates['preset'] ?? 'default',
            'startDate' => $dates['startDate'] ?? null,
            'endDate' => $dates['endDate'] ?? null,
        ];
    }

    /**
     * @return array<Component|ToggleButtons>
     */
    protected function widgetDatesComponents(): array
    {
        $state = $this->widgetDatesState();
        $custom = fn (Get $get): bool => $get('preset') === ResolveDateRange::CUSTOM;

        return [
            ToggleButtons::make('preset')
                ->id("widget-dates-{$this->dashboardItemId}-preset")
                ->hiddenLabel()
                ->options(fn (): array => $this->widgetDatesOptions())
                ->default($state['preset'])
                ->inline()
                ->required()
                ->live(),
            Grid::make(2)
                ->schema([
                    DatePicker::make('startDate')
                        ->id("widget-dates-{$this->dashboardItemId}-start")
                        ->label(__('filament-dashboards::dashboards.dates.from'))
                        ->default($state['startDate'])
                        ->required($custom),
                    DatePicker::make('endDate')
                        ->id("widget-dates-{$this->dashboardItemId}-end")
                        ->label(__('filament-dashboards::dashboards.dates.until'))
                        ->default($state['endDate'])
                        ->required($custom)
                        ->afterOrEqual('startDate'),
                ])
                ->visible($custom),
        ];
    }

    protected function widgetDatesAction(): Action
    {
        return FilamentDashboardsPlugin::get()->modifyAction(Action::make('widgetDates')
            ->label(__('filament-dashboards::dashboards.dates.widget'))
            ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::WIDGET_DATES))
            ->iconButton()
            ->color(fn (): string => $this->widgetDates() === null ? 'gray' : 'primary')
            ->visible(fn (): bool => $this->offersWidgetDates())
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel(__('filament-dashboards::dashboards.dates.apply'))
            ->fillForm(fn (): array => $this->widgetDatesState())
            ->schema(fn (): array => $this->widgetDatesComponents())
            ->action(function (array $data): void {
                $this->keepWidgetDates($data);
                $this->widgetDatesChanged();
            }), 'widget.dates');
    }

    protected function widgetDatesChanged(): void {}

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
     * @param  list<array<mixed>>  $parts
     */
    protected function ownRangeLabel(array $parts): ?string
    {
        $labels = [];

        foreach ($parts as $part) {
            if ($this->configString($part, 'dates_field') === null) {
                continue;
            }

            try {
                $range = app(ResolveWidgetRange::class)->handle($part);
            } catch (InvalidArgumentException) {
                return null;
            }

            if ($range === null) {
                return null;
            }

            $labels[] = $this->rangeLabel($range);
        }

        return count(array_unique($labels)) === 1 ? $labels[0] : null;
    }

    protected function rangeLabel(DateRange $range): string
    {
        $plugin = FilamentDashboardsPlugin::get();

        return $plugin->formatDate($range->start, 'range').' – '.$plugin->formatDate($range->end, 'range');
    }

    /**
     * @param  array<mixed>  $config
     */
    protected function dashboardDates(array $config): ?DateRange
    {
        if ($this->configString($config, 'dates_field') === null) {
            return null;
        }

        return app(ResolveWidgetRange::class)->handle($config)
            ?? app(ResolveDateRange::class)->handle($this->pageFilters);
    }

    protected function getPollingInterval(): ?string
    {
        $interval = $this->builderWidget()?->refresh_interval;
        $minimum = FilamentDashboardsPlugin::get()->getMinRefreshInterval();

        return filled($interval) && $minimum !== null ? max($minimum, $interval).'s' : null;
    }

    protected function formatNumber(int|float $value): string
    {
        return FilamentDashboardsPlugin::get()->formatNumber($value);
    }
}

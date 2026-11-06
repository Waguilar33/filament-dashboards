<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Resources\BuilderWidgets\Schemas;

use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Waguilar\FilamentDashboards\Actions\ResolveWidgetRange;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\Arithmetic;
use Waguilar\FilamentDashboards\Builder\Enums\FieldType;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Builder\Enums\Operator;
use Waguilar\FilamentDashboards\Builder\Enums\TimeGrouping;
use Waguilar\FilamentDashboards\Builder\Field;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;
use Waguilar\FilamentDashboards\Widgets\BuilderChartWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderFiguresWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderStatsWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderSummaryWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderTableWidget;

class BuilderWidgetForm
{
    private const T = 'filament-dashboards::dashboards.builder.fields.';

    /**
     * @return array<Component>
     */
    public static function components(BuilderWidgetType $type): array
    {
        return [
            Tabs::make()->contained(false)->tabs([
                Tab::make(__('filament-dashboards::dashboards.builder.tabs.edit'))->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label(__(self::T.'name'))
                            ->helperText(__(self::T.'name_helper'))
                            ->default($type->getLabel())
                            ->required()
                            ->maxLength(255),
                        Toggle::make('show_title')
                            ->label(__(self::T.'show_title'))
                            ->inline(false)
                            ->live()
                            ->visible($type === BuilderWidgetType::Stats),
                        TextInput::make('refresh_interval')
                            ->label(__(self::T.'refresh_interval'))
                            ->helperText(__(self::T.'refresh_interval_helper'))
                            ->suffix(__(self::T.'seconds'))
                            ->integer()
                            ->minValue(FilamentDashboardsPlugin::get()->getMinRefreshInterval() ?? 0)
                            ->visible(FilamentDashboardsPlugin::get()->getMinRefreshInterval() !== null),
                    ]),
                    // Config sits at the form root: under a 'config' key a stats card filter rule passes Livewire's nesting depth of 10.
                    Group::make(FilamentDashboardsPlugin::get()->modifyBuilderWidgetFields(self::configFields($type), $type)),
                ]),
                Tab::make(__('filament-dashboards::dashboards.builder.tabs.preview'))->schema([
                    Livewire::make(
                        self::component($type),
                        fn (Component $component): array => ['previewConfig' => self::config($component->getRootContainer()->getRawState()), 'previewType' => $type->value, 'previewName' => self::stringOrEmpty(self::state($component->getRootContainer()->getRawState())['name'] ?? null)],
                    )->key(fn (Component $component): string => 'builder-preview-'.md5((string) json_encode(self::config($component->getRootContainer()->getRawState())))),
                ]),
            ]),
        ];
    }

    /**
     * @return class-string<BuilderStatsWidget|BuilderChartWidget|BuilderTableWidget|BuilderSummaryWidget|BuilderFiguresWidget>
     */
    public static function component(BuilderWidgetType $type): string
    {
        return match ($type) {
            BuilderWidgetType::Stats => BuilderStatsWidget::class,
            BuilderWidgetType::Table => BuilderTableWidget::class,
            BuilderWidgetType::Summary => BuilderSummaryWidget::class,
            BuilderWidgetType::Figures => BuilderFiguresWidget::class,
            default => BuilderChartWidget::class,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(mixed $state): array
    {
        /** @var array<string, mixed> */
        return Arr::except(self::state($state), ['name', 'refresh_interval']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fill(BuilderWidget $widget): array
    {
        return [...$widget->config, 'name' => $widget->name, 'refresh_interval' => $widget->refresh_interval];
    }

    /**
     * @return array<string, mixed>
     */
    public static function state(mixed $state): array
    {
        $config = [];

        foreach (is_array($state) ? $state : [] as $key => $value) {
            $config[(string) $key] = $value;
        }

        return $config;
    }

    /**
     * @return array<Component>
     */
    protected static function configFields(BuilderWidgetType $type): array
    {
        return match ($type) {
            BuilderWidgetType::Stats => [
                Repeater::make('cards')
                    ->label(__(self::T.'cards'))
                    ->helperText(__(self::T.'cards_helper'))
                    ->addActionLabel(__(self::T.'add_card'))
                    ->minItems(1)
                    ->defaultItems(1)
                    ->itemLabel(fn (array $state): ?string => is_string($state['label'] ?? null) ? $state['label'] : null)
                    ->collapsible()
                    ->grid(2)
                    ->columns(2)
                    ->schema([
                        Hidden::make('key')->default(fn (): string => (string) Str::uuid()),
                        TextInput::make('label')->label(__(self::T.'label'))->live(onBlur: true)->maxLength(255)->columnSpanFull(),
                        self::sourceSelect(),
                        ...self::metricFields(),
                        ...self::datesFields(),
                        Toggle::make('compare')
                            ->label(__(self::T.'compare'))
                            ->helperText(__(self::T.'compare_helper'))
                            ->visible(fn (Get $get): bool => filled($get('dates_field'))),
                        TextInput::make('goal')
                            ->label(__(self::T.'goal'))
                            ->helperText(__(self::T.'goal_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn (Get $get): bool => blank($get('dates_field')) || ($get('period') ?? 'dashboard') !== 'dashboard'),
                        self::filters(ruleColumns: 1),
                    ]),
            ],
            BuilderWidgetType::Line, BuilderWidgetType::Bar => [
                Grid::make(2)->schema([
                    self::sourceSelect(),
                    self::fieldSelect('date_field', FieldType::Date)->label(__(self::T.'date_field'))->required(),
                    ...self::metricFields(),
                    Select::make('period')
                        ->label(__(self::T.'period'))
                        ->options(fn (): array => [
                            ...__('filament-dashboards::dashboards.builder.periods'),
                            ...array_diff_key(self::periodOptions(), __('filament-dashboards::dashboards.builder.periods')),
                            ResolveWidgetRange::OWN_RANGE => __('filament-dashboards::dashboards.builder.card_periods.custom'),
                        ])
                        ->default('12m')
                        ->selectablePlaceholder(false)
                        ->live(),
                    ...self::ownRangeFields(fn (Get $get): bool => $get('period') === ResolveWidgetRange::OWN_RANGE),
                    Select::make('grouping')
                        ->label(__(self::T.'grouping'))
                        ->options(TimeGrouping::class)
                        ->live(),
                    self::fieldSelect('split_field', FieldType::Choice, FieldType::Text, FieldType::Boolean)
                        ->label(__(self::T.'group_field'))
                        ->helperText(__(self::T.'split_field_helper', ['count' => FilamentDashboardsPlugin::get()->getBuilderLimit('series')])),
                    TextInput::make('label')->label(__(self::T.'label'))->live(onBlur: true)->maxLength(255),
                ]),
                self::filters(),
            ],
            BuilderWidgetType::Summary => [
                Grid::make(2)->schema([
                    self::sourceSelect(),
                    self::fieldSelect('group_field', FieldType::Choice, FieldType::Text, FieldType::Boolean)->label(__(self::T.'group_field'))->required(),
                    TextInput::make('limit')->label(__(self::T.'limit'))->integer()->minValue(1)->maxValue(FilamentDashboardsPlugin::get()->getBuilderLimit('summary'))->default(20)->live(onBlur: true),
                    ...self::datesFields(),
                    Toggle::make('total_row')->label(__(self::T.'total_row'))->live(),
                ]),
                Repeater::make('metrics')
                    ->label(__(self::T.'metrics'))
                    ->helperText(__(self::T.'metrics_helper'))
                    ->addActionLabel(__(self::T.'add_metric'))
                    ->minItems(1)
                    ->defaultItems(1)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        ...self::metricFields('../../source'),
                        TextInput::make('label')->label(__(self::T.'label'))->live(onBlur: true)->maxLength(255),
                        Toggle::make('percent')
                            ->label(__(self::T.'percent'))
                            ->live()
                            ->visible(fn (Get $get): bool => self::metric($get('metric'))?->sharesOfTotal() ?? false),
                    ]),
                self::filters(),
            ],
            BuilderWidgetType::Figures => [
                Repeater::make('columns')
                    ->label(__(self::T.'figure_columns'))
                    ->helperText(__(self::T.'figure_columns_helper'))
                    ->addActionLabel(__(self::T.'add_column'))
                    ->minItems(1)
                    ->defaultItems(1)
                    ->collapsible()
                    ->columns(2)
                    ->itemLabel(fn (array $state): ?string => is_string($state['label'] ?? null) ? $state['label'] : null)
                    ->schema([
                        Hidden::make('key')->default(fn (): string => (string) Str::uuid()),
                        TextInput::make('label')->label(__(self::T.'label'))->live(onBlur: true)->maxLength(255),
                        Select::make('kind')
                            ->label(__(self::T.'kind'))
                            ->options(fn (): array => __('filament-dashboards::dashboards.builder.kinds'))
                            ->default('figure')
                            ->selectablePlaceholder(false)
                            ->live(),
                        Group::make([
                            self::sourceSelect(),
                            ...self::metricFields(),
                            ...self::datesFields(),
                            self::filters(),
                        ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('kind') !== 'calculation'),
                        Group::make([
                            ...self::operandFields('left'),
                            Select::make('operator')
                                ->label(__(self::T.'operation'))
                                ->options(Arithmetic::class)
                                ->required()
                                ->live(),
                            ...self::operandFields('right'),
                        ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('kind') === 'calculation'),
                        Select::make('share_of')
                            ->label(__(self::T.'share_of'))
                            ->options(fn (Get $get): array => array_diff_key(self::columnOptions($get('../../columns')), [self::stringOrEmpty($get('key')) => true]))
                            ->live(),
                        Select::make('blank_rows')
                            ->label(__(self::T.'blank_rows'))
                            ->options(fn (Get $get): array => self::rowOptions($get('../../base_label'), $get('../../rows')))
                            ->multiple()
                            ->live(),
                    ]),
                TextInput::make('base_label')
                    ->label(__(self::T.'base_label'))
                    ->default(fn (): string => __('filament-dashboards::dashboards.builder.figures.base'))
                    ->required()
                    ->live(onBlur: true)
                    ->maxLength(255),
                Repeater::make('rows')
                    ->label(__(self::T.'figure_rows'))
                    ->helperText(__(self::T.'figure_rows_helper'))
                    ->addActionLabel(__(self::T.'add_row'))
                    ->defaultItems(0)
                    ->columns(3)
                    ->schema([
                        Hidden::make('key')->default(fn (): string => (string) Str::uuid()),
                        TextInput::make('label')->label(__(self::T.'label'))->required()->live(onBlur: true)->maxLength(255),
                        Select::make('operator')->label(__(self::T.'operation'))->options(Arithmetic::class)->required()->live(),
                        TextInput::make('number')->label(__(self::T.'number'))->numeric()->required()->live(onBlur: true),
                    ]),
            ],
            BuilderWidgetType::Scatter => [
                Grid::make(2)->schema([
                    self::sourceSelect(),
                    TextInput::make('label')->label(__(self::T.'label'))->live(onBlur: true)->maxLength(255),
                    self::fieldSelect('x_field', FieldType::Number)->label(__(self::T.'x_field'))->required(),
                    self::fieldSelect('y_field', FieldType::Number)->label(__(self::T.'y_field'))->required(),
                    ...self::datesFields(),
                ]),
                self::filters(),
            ],
            BuilderWidgetType::Table => [
                Grid::make(2)->schema([
                    self::sourceSelect(),
                    TextInput::make('limit')->label(__(self::T.'limit'))->integer()->minValue(1)->maxValue(FilamentDashboardsPlugin::get()->getBuilderLimit('table'))->default(10)->live(onBlur: true),
                    self::fieldSelect('columns', ...FieldType::cases())->label(__(self::T.'columns'))->multiple()->required()->columnSpanFull(),
                    self::fieldSelect('sort_field', ...FieldType::cases())->label(__(self::T.'sort_field')),
                    Select::make('sort_direction')
                        ->label(__(self::T.'sort_direction'))
                        ->options(fn (): array => __('filament-dashboards::dashboards.builder.directions'))
                        ->default('desc')
                        ->selectablePlaceholder(false)
                        ->live(),
                    ...self::datesFields(),
                ]),
                self::filters(),
            ],
            default => [
                Grid::make(2)->schema([
                    self::sourceSelect(),
                    self::fieldSelect('group_field', FieldType::Choice, FieldType::Text, FieldType::Boolean)->label(__(self::T.'group_field'))->required(),
                    ...self::metricFields(),
                    TextInput::make('limit')->label(__(self::T.'limit'))->integer()->minValue(1)->maxValue(FilamentDashboardsPlugin::get()->getBuilderLimit('breakdown'))->default(10)->live(onBlur: true),
                    TextInput::make('label')->label(__(self::T.'label'))->live(onBlur: true)->maxLength(255),
                    ...self::datesFields(),
                ]),
                self::filters(),
            ],
        };
    }

    protected static function sourceSelect(): Select
    {
        return Select::make('source')
            ->label(__(self::T.'source'))
            ->options(function (): array {
                $options = [];

                foreach (FilamentDashboardsPlugin::get()->getDataSourcesFor(DashboardResource::user(), Filament::getTenant()) as $key => $source) {
                    $options[$source->group() ?? __('filament-dashboards::dashboards.builder.fields.source')][$key] = $source->label();
                }

                return $options;
            })
            ->required()
            ->live();
    }

    /**
     * @return array<Component>
     */
    protected static function metricFields(string $sourcePath = 'source'): array
    {
        return [
            Select::make('metric')
                ->label(__(self::T.'metric'))
                ->options(Metric::class)
                ->default(Metric::Count->value)
                ->selectablePlaceholder(false)
                ->required()
                ->live(),
            Select::make('field')
                ->options(fn (Get $get): array => self::fieldOptions(self::source($get($sourcePath)), FieldType::Number))
                ->live()
                ->label(__(self::T.'field'))
                ->required()
                ->visible(fn (Get $get): bool => self::metric($get('metric'))?->requiresNumberField() ?? false),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function periodOptions(?string $dashboardLabel = null): array
    {
        $options = $dashboardLabel === null ? [] : ['dashboard' => $dashboardLabel];

        foreach (FilamentDashboardsPlugin::get()->getDatePeriods() as $key => $period) {
            $options[$key] = $period->label();
        }

        return $options;
    }

    /**
     * @return array<Component>
     */
    protected static function datesFields(): array
    {
        return [
            self::datesField(),
            Select::make('period')
                ->label(__(self::T.'card_period'))
                ->options(fn (): array => [
                    ...self::periodOptions(__('filament-dashboards::dashboards.builder.periods.dashboard')),
                    ResolveWidgetRange::OWN_RANGE => __('filament-dashboards::dashboards.builder.card_periods.custom'),
                ])
                ->default('dashboard')
                ->selectablePlaceholder(false)
                ->live()
                ->visible(fn (Get $get): bool => filled($get('dates_field'))),
            ...self::ownRangeFields(fn (Get $get): bool => filled($get('dates_field')) && $get('period') === ResolveWidgetRange::OWN_RANGE),
        ];
    }

    /**
     * @param  Closure(Get): bool  $visible
     * @return array<Component>
     */
    protected static function ownRangeFields(Closure $visible): array
    {
        return [
            DatePicker::make('period_start')
                ->label(__('filament-dashboards::dashboards.dates.from'))
                ->required()
                ->live()
                ->visible($visible),
            DatePicker::make('period_end')
                ->label(__('filament-dashboards::dashboards.dates.until'))
                ->required()
                ->afterOrEqual('period_start')
                ->live()
                ->visible($visible),
        ];
    }

    protected static function datesField(): Select
    {
        return self::fieldSelect('dates_field', FieldType::Date)
            ->label(__(self::T.'dates_field'))
            ->helperText(__(self::T.'dates_field_helper'));
    }

    protected static function fieldSelect(string $name, FieldType ...$types): Select
    {
        return Select::make($name)
            ->options(fn (Get $get): array => self::fieldOptions(self::source($get('source')), ...$types))
            ->live();
    }

    protected static function filters(int $ruleColumns = 3): Repeater
    {
        return Repeater::make('filters')
            ->label(__(self::T.'filters'))
            ->helperText(__(self::T.'filters_helper'))
            ->addActionLabel(__(self::T.'add_group'))
            ->defaultItems(0)
            ->reorderable(false)
            ->columnSpanFull()
            ->schema([
                Repeater::make('rules')
                    ->hiddenLabel()
                    ->addActionLabel(__(self::T.'add_rule'))
                    ->minItems(1)
                    ->defaultItems(1)
                    ->reorderable(false)
                    ->columns($ruleColumns)
                    ->schema([
                        Select::make('field')
                            ->label(__(self::T.'field'))
                            ->options(fn (Get $get): array => self::fieldOptions(self::source($get('../../../../source')), ...FieldType::cases()))
                            ->required()
                            ->live(),
                        Select::make('operator')
                            ->label(__(self::T.'operator'))
                            ->options(fn (Get $get): array => self::operatorOptions(self::field(self::source($get('../../../../source')), $get('field'))))
                            ->required()
                            ->live(),
                        TextInput::make('value')
                            ->label(__(self::T.'value'))
                            ->type(fn (Get $get): string => self::field(self::source($get('../../../../source')), $get('field'))?->type === FieldType::Date ? 'date' : 'text')
                            ->required()
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => self::needsValue($get) && ! self::isChoice($get)),
                        Select::make('choice')
                            ->label(__(self::T.'value'))
                            ->options(fn (Get $get): array => self::choiceOptions(self::field(self::source($get('../../../../source')), $get('field'))))
                            ->required()
                            ->live()
                            ->visible(fn (Get $get): bool => self::needsValue($get) && self::isChoice($get)),
                    ]),
            ]);
    }

    /**
     * @return list<Component>
     */
    protected static function operandFields(string $side): array
    {
        return [
            Select::make($side)
                ->label(__(self::T.$side))
                ->options(fn (Get $get): array => [
                    ...self::columnOptions($get('../../columns'), before: self::stringOrEmpty($get('key'))),
                    BuilderFiguresWidget::NUMBER => __(self::T.'a_number'),
                ])
                ->required()
                ->live(),
            TextInput::make("{$side}_number")
                ->label(__(self::T.'number'))
                ->numeric()
                ->required()
                ->live(onBlur: true)
                ->visible(fn (Get $get): bool => $get($side) === BuilderFiguresWidget::NUMBER),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function columnOptions(mixed $columns, ?string $before = null): array
    {
        $options = [];

        foreach (is_array($columns) ? array_values($columns) : [] as $index => $column) {
            $key = is_array($column) && is_string($column['key'] ?? null) ? $column['key'] : (string) $index;

            if ($key === $before) {
                break;
            }

            $label = is_array($column) ? self::stringOrEmpty($column['label'] ?? null) : '';
            $options[$key] = $label === '' ? __(self::T.'column_number', ['number' => $index + 1]) : $label;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected static function rowOptions(mixed $baseLabel, mixed $rows): array
    {
        $options = [BuilderFiguresWidget::BASE_ROW => self::stringOrEmpty($baseLabel)];

        foreach (is_array($rows) ? array_values($rows) : [] as $index => $row) {
            if (is_array($row)) {
                $options[is_string($row['key'] ?? null) ? $row['key'] : "row_{$index}"] = self::stringOrEmpty($row['label'] ?? null);
            }
        }

        return $options;
    }

    protected static function source(mixed $key): ?DataSource
    {
        return is_string($key) ? (FilamentDashboardsPlugin::get()->getDataSourcesFor(DashboardResource::user(), Filament::getTenant())[$key] ?? null) : null;
    }

    protected static function field(?DataSource $source, mixed $name): ?Field
    {
        foreach ($source?->fields() ?? [] as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected static function fieldOptions(?DataSource $source, FieldType ...$types): array
    {
        $options = [];

        foreach ($source?->fields() ?? [] as $field) {
            if (in_array($field->type, $types, true)) {
                $options[$field->name] = $field->label;
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected static function operatorOptions(?Field $field): array
    {
        $options = [];

        foreach ($field === null ? [] : Operator::forType($field->type) as $operator) {
            $options[$operator->value] = $operator->getLabel();
        }

        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    protected static function choiceOptions(?Field $field): array
    {
        return match ($field?->type) {
            FieldType::Boolean => ['1' => $field->valueLabel(true), '0' => $field->valueLabel(false)],
            FieldType::Choice => $field->options,
            default => [],
        };
    }

    protected static function needsValue(Get $get): bool
    {
        return Operator::tryFrom(self::stringOrEmpty($get('operator')))?->requiresValue() ?? false;
    }

    protected static function isChoice(Get $get): bool
    {
        return in_array(self::field(self::source($get('../../../../source')), $get('field'))?->type, [FieldType::Choice, FieldType::Boolean], true);
    }

    protected static function metric(mixed $state): ?Metric
    {
        return $state instanceof Metric ? $state : Metric::tryFrom(self::stringOrEmpty($state));
    }

    protected static function stringOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}

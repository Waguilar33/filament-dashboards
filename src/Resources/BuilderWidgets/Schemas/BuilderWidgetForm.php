<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Resources\BuilderWidgets\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Enums\FieldType;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\Builder\Enums\Operator;
use Waguilar\FilamentDashboards\Builder\Enums\TimeGrouping;
use Waguilar\FilamentDashboards\Builder\Field;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Widgets\BuilderChartWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderStatsWidget;
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
                        TextInput::make('refresh_interval')
                            ->label(__(self::T.'refresh_interval'))
                            ->helperText(__(self::T.'refresh_interval_helper'))
                            ->suffix(__(self::T.'seconds'))
                            ->integer()
                            ->minValue(5),
                    ]),
                    Group::make(self::configFields($type))->statePath('config'),
                ]),
                Tab::make(__('filament-dashboards::dashboards.builder.tabs.preview'))->schema([
                    Livewire::make(
                        self::component($type),
                        fn (Get $get): array => ['previewConfig' => self::state($get('config')), 'previewType' => $type->value],
                    )->key(fn (Get $get): string => 'builder-preview-'.md5((string) json_encode($get('config')))),
                ]),
            ]),
        ];
    }

    /**
     * @return class-string<BuilderStatsWidget|BuilderChartWidget|BuilderTableWidget>
     */
    public static function component(BuilderWidgetType $type): string
    {
        return match ($type) {
            BuilderWidgetType::Stats => BuilderStatsWidget::class,
            BuilderWidgetType::Table => BuilderTableWidget::class,
            default => BuilderChartWidget::class,
        };
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
                    ->schema([
                        self::sourceSelect(),
                        ...self::metricFields(),
                        self::filters(),
                        TextInput::make('label')->label(__(self::T.'label'))->live(onBlur: true)->maxLength(255),
                    ]),
            ],
            BuilderWidgetType::Line, BuilderWidgetType::Bar => [
                Grid::make(2)->schema([
                    self::sourceSelect(),
                    self::fieldSelect('date_field', FieldType::Date)->label(__(self::T.'date_field'))->required(),
                    ...self::metricFields(),
                    Select::make('period')
                        ->label(__(self::T.'period'))
                        ->options(fn (): array => __('filament-dashboards::dashboards.builder.periods'))
                        ->default('12m')
                        ->selectablePlaceholder(false)
                        ->live(),
                    Select::make('grouping')
                        ->label(__(self::T.'grouping'))
                        ->options(TimeGrouping::class)
                        ->live(),
                    TextInput::make('label')->label(__(self::T.'label'))->live(onBlur: true)->maxLength(255),
                ]),
                self::filters(),
            ],
            BuilderWidgetType::Scatter => [
                Grid::make(2)->schema([
                    self::sourceSelect(),
                    TextInput::make('label')->label(__(self::T.'label'))->live(onBlur: true)->maxLength(255),
                    self::fieldSelect('x_field', FieldType::Number)->label(__(self::T.'x_field'))->required(),
                    self::fieldSelect('y_field', FieldType::Number)->label(__(self::T.'y_field'))->required(),
                ]),
                self::filters(),
            ],
            BuilderWidgetType::Table => [
                Grid::make(2)->schema([
                    self::sourceSelect(),
                    TextInput::make('limit')->label(__(self::T.'limit'))->integer()->minValue(1)->maxValue(100)->default(10)->live(onBlur: true),
                    self::fieldSelect('columns', ...FieldType::cases())->label(__(self::T.'columns'))->multiple()->required()->columnSpanFull(),
                    self::fieldSelect('sort_field', ...FieldType::cases())->label(__(self::T.'sort_field')),
                    Select::make('sort_direction')
                        ->label(__(self::T.'sort_direction'))
                        ->options(fn (): array => __('filament-dashboards::dashboards.builder.directions'))
                        ->default('desc')
                        ->selectablePlaceholder(false)
                        ->live(),
                ]),
                self::filters(),
            ],
            default => [
                Grid::make(2)->schema([
                    self::sourceSelect(),
                    self::fieldSelect('group_field', FieldType::Choice, FieldType::Text, FieldType::Boolean)->label(__(self::T.'group_field'))->required(),
                    ...self::metricFields(),
                    TextInput::make('limit')->label(__(self::T.'limit'))->integer()->minValue(1)->maxValue(20)->default(10)->live(onBlur: true),
                    TextInput::make('label')->label(__(self::T.'label'))->live(onBlur: true)->maxLength(255),
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

                foreach (FilamentDashboardsPlugin::get()->getDataSources() as $key => $source) {
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
    protected static function metricFields(): array
    {
        return [
            Select::make('metric')
                ->label(__(self::T.'metric'))
                ->options(Metric::class)
                ->default(Metric::Count->value)
                ->selectablePlaceholder(false)
                ->required()
                ->live(),
            self::fieldSelect('field', FieldType::Number)
                ->label(__(self::T.'field'))
                ->required()
                ->visible(fn (Get $get): bool => self::metric($get('metric'))?->requiresNumberField() ?? false),
        ];
    }

    protected static function fieldSelect(string $name, FieldType ...$types): Select
    {
        return Select::make($name)
            ->options(fn (Get $get): array => self::fieldOptions(self::source($get('source')), ...$types))
            ->live();
    }

    protected static function filters(): Repeater
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
                    ->columns(3)
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

    protected static function source(mixed $key): ?DataSource
    {
        return is_string($key) ? (FilamentDashboardsPlugin::get()->getDataSources()[$key] ?? null) : null;
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

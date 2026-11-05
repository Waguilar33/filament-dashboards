<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Filament\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Support\Arr;
use Waguilar\FilamentDashboards\Actions\SaveBuilderWidget;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Schemas\BuilderWidgetForm;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class BuildWidgetAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'buildWidget';
    }

    /**
     * @return array{heading: string, description: string, carousel: bool, options: array<string, array{label: string, description: string, preview: null, kind: string}>}
     */
    public static function pickerSection(string $prefix = ''): array
    {
        $options = [];

        foreach (BuilderWidgetType::cases() as $type) {
            $options[$prefix.$type->value] = ['label' => $type->getLabel(), 'description' => $type->getDescription(), 'preview' => null, 'kind' => $type->preview()];
        }

        return [
            'heading' => __('filament-dashboards::dashboards.builder.new.heading'),
            'description' => __('filament-dashboards::dashboards.builder.new.description'),
            'carousel' => false,
            'options' => $options,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading(fn (array $arguments): string => self::type($arguments)->getLabel())
            ->schema(fn (array $arguments): array => BuilderWidgetForm::components(self::type($arguments)))
            ->action(function (array $data, array $arguments, Action $action): void {
                $action->record(app(SaveBuilderWidget::class)->handle(
                    SaveBuilderWidget::newFor(DashboardResource::user(), Filament::getTenant(), FilamentDashboardsPlugin::currentPanel()->getId()),
                    self::type($arguments),
                    $data,
                ));
            });
    }

    /**
     * @param  array<mixed>  $arguments
     */
    protected static function type(array $arguments): BuilderWidgetType
    {
        return BuilderWidgetType::from(Arr::string($arguments, 'type'));
    }
}

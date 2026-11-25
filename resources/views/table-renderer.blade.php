@php
    use Filament\Support\Enums\IconSize;
    use Filament\Support\Facades\FilamentIcon;
    use Filament\Support\Icons\Heroicon;
    use Filament\Support\View\ComponentAttributeBag;
    use Filament\Tables\View\TablesIconAlias;

    $result = $this->tableResult();
    $heading = $this->builderHeading();
    $description = $this->builderDescription();
    $actions = $this->getVisibleBuilderHeaderActions();
    $hasHeader = filled($heading) || filled($description) || $actions;
    $pollingInterval = $this->builderPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
            ->class(['fi-wi-table', 'fi-dashboards-table-renderer'])
    "
>
    <div class="fi-ta">
        <div
            @class([
                'fi-ta-ctn',
                'fi-ta-ctn-with-header' => $hasHeader,
                'fi-ta-ctn-with-footer' => $result->isEmpty(),
            ])
        >
            <div class="fi-ta-main">
                @if ($hasHeader)
                    <div class="fi-ta-header-ctn">
                        <div @class(['fi-ta-header', 'fi-ta-header-adaptive-actions-position' => $actions])>
                            @if (filled($heading) || filled($description))
                                <div>
                                    @if (filled($heading))
                                        <h2 class="fi-ta-header-heading">{{ $heading }}</h2>
                                    @endif

                                    @if (filled($description))
                                        <p class="fi-ta-header-description">{{ $description }}</p>
                                    @endif
                                </div>
                            @endif

                            @if ($actions)
                                <div class="fi-ta-actions fi-align-start fi-wrapped">
                                    @foreach ($actions as $action)
                                        {{ $action }}
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($result->isEmpty())
                    <div class="fi-ta-empty-state" role="status">
                        <div class="fi-ta-empty-state-content">
                            <div class="fi-ta-empty-state-icon-bg">
                                {{ \Filament\Support\generate_icon_html(FilamentIcon::resolve(TablesIconAlias::EMPTY_STATE) ?? Heroicon::OutlinedXMark, size: IconSize::Large) }}
                            </div>

                            <h3 class="fi-ta-empty-state-heading">
                                {{ $this->builderEmptyStateHeading() }}
                            </h3>

                            @if (filled($emptyDescription = $this->builderEmptyStateDescription()))
                                <p class="fi-ta-empty-state-description">{{ $emptyDescription }}</p>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="fi-ta-content-ctn">
                        {{ $this->body($result) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>

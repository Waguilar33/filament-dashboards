@php
    use Filament\Support\Icons\Heroicon;
    use function Filament\Support\generate_icon_html;
@endphp

<div
    x-data="{ state: $wire.$entangle(@js($getStatePath())) }"
    class="fi-dashboards-picker"
>
    @foreach ($getSections() as $section)
        <section class="fi-dashboards-picker" x-data="{ scroll(direction) { $refs.track.scrollBy({ left: direction * $refs.track.clientWidth, behavior: 'smooth' }) } }">
            <div class="fi-dashboards-picker-header">
                <div>
                    <h3 class="fi-dashboards-picker-heading">{{ $section['heading'] }}</h3>
                    <p class="fi-dashboards-picker-description">{{ $section['description'] }}</p>
                </div>

                @if ($section['carousel'])
                    <div class="fi-dashboards-picker-arrows">
                        <x-filament::icon-button :icon="Heroicon::ChevronLeft" :label="__('filament-dashboards::dashboards.actions.previous')" x-on:click="scroll(-1)" />
                        <x-filament::icon-button :icon="Heroicon::ChevronRight" :label="__('filament-dashboards::dashboards.actions.next')" x-on:click="scroll(1)" />
                    </div>
                @endif
            </div>

            <div x-ref="track" @class(['fi-dashboards-picker-track' => $section['carousel'], 'fi-dashboards-picker-grid' => ! $section['carousel']])>
                @foreach ($section['options'] as $value => $option)
                    <button
                        type="button"
                        class="fi-dashboards-card"
                        x-bind:class="{ 'fi-selected': state === @js($value) }"
                        x-on:click="state = @js($value)"
                        wire:key="picker-option-{{ $value }}"
                    >
                        <span class="fi-dashboards-card-check" x-show="state === @js($value)" x-cloak>
                            {{ generate_icon_html(Heroicon::CheckCircle) }}
                        </span>

                        <span class="fi-dashboards-card-preview">
                            @if (filled($option['preview']))
                                <img src="{{ $option['preview'] }}" alt="" />
                            @else
                                @include('filament-dashboards::previews.' . $option['kind'])
                            @endif
                        </span>

                        <span class="fi-dashboards-card-title">{{ $option['label'] }}</span>

                        @if (filled($option['description']))
                            <span class="fi-dashboards-card-description">{{ $option['description'] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </section>
    @endforeach
</div>

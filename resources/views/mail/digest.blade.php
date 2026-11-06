<x-mail::message>
# {{ $dashboardName }}

{{ $rangeLabel }}

@foreach ($sections as $section)
## {{ $section['name'] }}

<x-mail::table>
| | |
|:--|--:|
@foreach ($section['cards'] as $card)
| {{ $card->label }}@if ($card->description)<br><small>{{ $card->description }}</small>@endif | **{{ $card->formatted }}** |
@endforeach
</x-mail::table>
@endforeach

@if ($others !== [])
{{ __('filament-dashboards::dashboards.digest.others', ['widgets' => implode(', ', $others)]) }}
@endif

<x-mail::button :url="$url">
{{ __('filament-dashboards::dashboards.digest.open') }}
</x-mail::button>
</x-mail::message>

@props(['cols', 'headers' => [], 'empty' => false, 'emptyMessage' => 'Belum ada data.'])
{{-- The column headers also travel as --l1, --l2… so the phone layout (admin/components.css) labels every cell of a row card. --}}
@php($labels = collect($headers)->values()->map(fn ($h, $i) => '--l'.($i + 1).": '".addcslashes((string) $h, "'\\")."'")->join('; '))

<div {{ $attributes->class('adm-list')->merge(['style' => '--cols: '.$cols.($labels ? '; '.$labels : '')]) }}>
    @if($headers)
        <div class="adm-list-head" aria-hidden="true">
            @foreach($headers as $header)<span>{{ $header }}</span>@endforeach
        </div>
    @endif
    @if($empty)
        <div class="adm-list-empty">{{ $emptyMessage }}</div>
    @else
        {{ $slot }}
    @endif
</div>

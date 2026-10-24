{{--
    A sales screen: a header band (close, title, actions), then each child renders as a full-width band (sales.css).
    The sales app has two pages (Kerja, Lainnya); everything else opens as a sheet over them. A screen with `back` is a sheet: its close button goes back in history,
    which the sheet host turns into closing the sheet — the phone's Back button does the same.
--}}
@props(['title' => null, 'back' => null])

<div {{ $attributes->merge(['class' => 'sales-page']) }}>
    @if($title !== null || $back || isset($actions))
    <header class="sales-head">
        @if($back)
            <button type="button" class="sales-back" aria-label="Tutup" x-data x-on:click="history.state?.salesSheet ? history.back() : $dispatch('sheet-close')"><x-lucide-arrow-left class="ico" /></button>
        @endif
        <h1>{{ $title }}</h1>
        @isset($actions)
            <div class="sales-head-actions">{{ $actions }}</div>
        @endisset
    </header>
    @endif
    {{ $slot }}
</div>

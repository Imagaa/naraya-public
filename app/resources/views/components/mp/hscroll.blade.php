{{--
    A themed horizontal scroll row: no browser scrollbar, edge fades on the
    side that has more, ‹ › buttons from 1024px (Alpine `hscroll`). Extra
    attributes (class, role, aria-*) go on the scrolling track itself.
--}}
@props(['label' => 'daftar'])
<div class="hscroll-wrap" x-data="hscroll">
    <button type="button" class="hscroll-btn hscroll-prev" x-show="canLeft" x-cloak x-on:click="go(-1)" aria-label="Geser {{ $label }} ke kiri"><x-lucide-chevron-left class="ico" /></button>
    <div {{ $attributes->merge(['class' => 'hscroll']) }} x-ref="track" :class="{ 'can-left': canLeft, 'can-right': canRight }">
        {{ $slot }}
    </div>
    <button type="button" class="hscroll-btn hscroll-next" x-show="canRight" x-cloak x-on:click="go(1)" aria-label="Geser {{ $label }} ke kanan"><x-lucide-chevron-right class="ico" /></button>
</div>

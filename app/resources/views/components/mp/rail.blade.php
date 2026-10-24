{{-- A product rail: swipe on phones, 5 per view with ‹ › from 1024px; skipped below 3 products. --}}
@props(['products', 'title', 'subtitle' => null, 'moreUrl' => null, 'tone' => 'plain', 'cartQty' => [], 'alertIds' => [], 'keyPrefix' => 'rail'])
@if($products->count() >= 3)
    <x-mp.section :tone="$tone" :title="$title" :subtitle="$subtitle" :more-url="$moreUrl">
        <div class="rail-wrap" x-data="hscroll">
            <button type="button" class="rail-nav rail-prev" x-show="canLeft" x-cloak x-on:click="go(-1)" aria-label="Sebelumnya"><x-lucide-chevron-left class="ico" /></button>
            <div class="rail hscroll" x-ref="track" :class="{ 'can-left': canLeft, 'can-right': canRight }">
                @foreach($products as $product)
                    <x-mp.product-card :product="$product" :qty="$cartQty[$product->id] ?? 0" :alerted="in_array($product->id, $alertIds)" wire:key="{{ $keyPrefix }}-{{ $product->id }}" />
                @endforeach
            </div>
            <button type="button" class="rail-nav rail-next" x-show="canRight" x-cloak x-on:click="go(1)" aria-label="Berikutnya"><x-lucide-chevron-right class="ico" /></button>
        </div>
    </x-mp.section>
@endif

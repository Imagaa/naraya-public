{{--
    A quantity entered in whole units (lembar) with − [n] +; the wire:model (passed through the attributes) gets the
    total in pcs (n × pcs per unit), which is what the stock books count. `max` is in pcs. Used wherever goods move
    by the unit: taking stock, titip, stok awal, nota jual langsung.
--}}
@props(['product', 'max' => null, 'value' => null])
@php($ppu = $product->ppu())
@php($unit = $product->unit ?: 'pcs')
@php($n = $value !== null && (int) $value > 0 ? intdiv((int) $value, $ppu) : '')
@php($top = $max !== null ? intdiv((int) $max, $ppu) : null)

<span class="unit-step" x-data="{ n: '{{ $n }}', top: {{ $top ?? 'null' }},
    set(v) { v = Math.max(0, parseInt(v) || 0); if (this.top !== null) v = Math.min(v, this.top); this.n = v ? String(v) : ''; this.sync() },
    sync() { this.$refs.t.value = this.n === '' ? '' : (parseInt(this.n) || 0) * {{ $ppu }}; this.$refs.t.dispatchEvent(new Event('input', { bubbles: true })) } }">
    <span class="step step-input">
        <button type="button" aria-label="Kurangi {{ $product->name }}" x-on:click="set((parseInt(n) || 0) - 1)"><x-lucide-minus class="ico" /></button>
        <input type="number" min="0" step="1" inputmode="numeric" @if($top !== null) max="{{ $top }}" @endif placeholder="0" x-model="n" x-on:change="set(n)" x-on:input="sync()"
            aria-label="Jumlah {{ $unit }} {{ $product->name }}" @if($n !== '') value="{{ $n }}" @endif>
        <button type="button" aria-label="Tambah {{ $product->name }}" x-on:click="set((parseInt(n) || 0) + 1)" :disabled="top !== null && (parseInt(n) || 0) >= top"><x-lucide-plus class="ico" /></button>
    </span>
    <small>{{ $unit }}</small>
    <input type="hidden" x-ref="t" {{ $attributes }}>
</span>

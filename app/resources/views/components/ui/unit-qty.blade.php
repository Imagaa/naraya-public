{{--
    A quantity in pcs. When a unit holds several pieces
    (e.g. 1 lembar = 12 pcs) it reads as "[ ] lembar [ ] pcs"; the wire:model (passed through the attributes)
    holds the total in pcs. A one-piece unit is the plain stepper.
--}}
@props(['product', 'max' => null, 'value' => null])
@php($ppu = $product->ppu())
@php($v = (int) $value) {{-- the bound value, rendered on the server so the fields are filled before any JS runs --}}

@if($ppu === 1)
    <x-ui.qty-stepper :label="$product->name" :max="$max" :attributes="$value !== null ? $attributes->merge(['value' => $v]) : $attributes" />
@else
    <span class="unit-qty" x-data="{ ppu: {{ $ppu }}, l: '{{ $v ? intdiv($v, $ppu) : '' }}', p: '{{ $v % $ppu ?: '' }}',
        init() { this.$nextTick(() => { const raw = this.$refs.t.value; const v = raw === '' ? {{ $v }} : parseInt(raw) || 0; this.l = v ? Math.floor(v / this.ppu) : (raw === '' && ! {{ $v }} ? '' : 0); this.p = v % this.ppu || ''; }) },
        sync() { const t = (parseInt(this.l) || 0) * this.ppu + (parseInt(this.p) || 0); this.$refs.t.value = this.l === '' && this.p === '' ? '' : t; this.$refs.t.dispatchEvent(new Event('input', { bubbles: true })) } }">
        <input type="number" min="0" step="1" inputmode="numeric" class="input" x-model="l" x-on:input="sync()" aria-label="Jumlah {{ $product->unit ?: 'satuan' }} {{ $product->name }}" placeholder="0" @if($v >= $ppu) value="{{ intdiv($v, $ppu) }}" @endif>
        <small>{{ $product->unit ?: 'satuan' }}</small>
        <input type="number" min="0" step="1" inputmode="numeric" class="input" x-model="p" x-on:input="sync()" aria-label="Jumlah pcs {{ $product->name }}" placeholder="0" @if($v % $ppu) value="{{ $v % $ppu }}" @endif>
        <small>pcs</small>
        <input type="hidden" x-ref="t" {{ $attributes }}>
    </span>
@endif

{{-- Quantity stepper: − [native number input] +. Typing still works; wire:model goes on the input via the attributes. --}}
@props(['label', 'max' => null])

<span class="step step-input" x-data>
    <button type="button" aria-label="Kurangi {{ $label }}" x-on:click="$refs.n.stepDown(); $refs.n.dispatchEvent(new Event('input', { bubbles: true }))"><x-lucide-minus class="ico" /></button>
    <input x-ref="n" type="number" min="0" step="1" inputmode="numeric" @if($max !== null) max="{{ $max }}" @endif aria-label="Jumlah {{ $label }}" {{ $attributes }}>
    <button type="button" aria-label="Tambah {{ $label }}" x-on:click="$refs.n.stepUp(); $refs.n.dispatchEvent(new Event('input', { bubbles: true }))"><x-lucide-plus class="ico" /></button>
</span>

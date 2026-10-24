@props(['label' => null])

@if($label)
    <div class="adm-chip">
        Antrean: {{ $label }}
        <button type="button" wire:click="clearQueue" aria-label="Hapus filter antrean"><x-lucide-x width="14" height="14" /></button>
    </div>
@endif

<form wire:submit="logVisit" class="sales-form" x-data="{ q: '' }">
    @unless($fixed)
        <div class="field">
            <label for="visit-store-q">Toko</label>
            <input id="visit-store-q" type="search" class="input" placeholder="Cari toko…" autocomplete="off" x-model="q">
            <div class="sales-pick" role="radiogroup" aria-label="Pilih toko">
                @forelse($buyers as $buyer)
                    <label wire:key="pick-{{ $buyer->id }}" data-name="{{ mb_strtolower($buyer->name) }}" x-show="$el.dataset.name.includes(q.toLowerCase())">
                        <input type="radio" name="visit-buyer" value="{{ $buyer->id }}" wire:model="buyerId">
                        {{ $buyer->name }}
                    </label>
                @empty
                    <p class="sales-note">Belum ada toko yang ditugaskan ke Anda.</p>
                @endforelse
            </div>
        </div>
    @endunless
    <x-ui.field-error name="buyerId" />

    <x-ui.input label="Catatan (opsional)" wire:model="notes" />

    <div wire:key="visit-photo-{{ $saved }}">
        <x-ui.camera-photo wire-model="photo" label="Foto (opsional)" />
    </div>
    <x-ui.field-error name="photo" />

    <x-ui.button type="submit" variant="primary">Simpan Kunjungan</x-ui.button>
</form>

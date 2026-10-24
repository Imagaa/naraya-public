<x-sales.page :title="$buyer->name" back="1">
    <section>
        @if($photo = $profile->storePhotoUrl())
            <img src="{{ $photo }}" alt="Foto {{ $buyer->name }}" class="sales-store-photo" loading="lazy">
        @endif
        @if($profile->owner_name)<p class="sales-note">Pemilik: {{ $profile->owner_name }}</p>@endif
        <p class="sales-note">{{ $profile->address ?? 'Alamat belum diisi' }}</p>
        @unless($profile->hasCompleteStoreData())
            <p><x-ui.tag variant="danger">Data toko belum lengkap</x-ui.tag></p>
        @endunless
        <x-sales.contact :phone="$profile->phone" :lat="$profile->latitude" :lng="$profile->longitude" :address="$profile->address" />
    </section>

    @if(! $opened)
        {{-- first time this store is opened in the app: what of ours is already there, before anything else --}}
        <section class="work-card">
            <p class="sales-note"><b>Langkah pertama di toko ini:</b> catat barang kita yang masih ada di toko dari sebelumnya. Setelah itu kunjungan & nota bisa dibuat.</p>
            <x-ui.button variant="primary" class="btn-block" x-data x-on:click="$dispatch('sheet-open', { sheet: 'stok-awal', for: {{ $buyer->id }} })">Catat barang di toko</x-ui.button>
            @if($stop)<x-ui.button variant="secondary" wire:click="$set('closing', true)">Toko tutup</x-ui.button>@endif
        </section>
    @else
        <section class="work-card">
            <div class="work-head"><h2>Titipan di toko</h2><span class="work-times">{{ $atStore->count() }} barang</span></div>
            @if($lastVisit)<p class="sales-note">Kunjungan terakhir: <b>{{ $lastVisit->wib()->translatedFormat('j M Y, H:i') }}</b></p>@endif
            @if($atStore->isNotEmpty())
                <ul class="store-stock">
                    @foreach($atStore as $id => $q)<li wire:key="at-{{ $id }}"><span>{{ $atStoreProducts[$id]->name ?? '?' }}</span><b>{{ $atStoreProducts[$id]?->qtyLabel($q) }}</b></li>@endforeach
                </ul>
            @endif
            <div class="work-actions">
                <x-ui.button variant="primary" x-data x-on:click="$dispatch('sheet-open', { sheet: 'titip', for: {{ $buyer->id }} })">Kunjungan titip jual</x-ui.button>
                <x-ui.button variant="secondary" x-data x-on:click="$dispatch('sheet-open', { sheet: 'nota', for: {{ $buyer->id }} })">Nota jual langsung</x-ui.button>
                <x-ui.button variant="secondary" x-data x-on:click="$dispatch('sheet-open', { sheet: 'investasi', for: {{ $buyer->id }} })"><x-lucide-trending-up class="ico" />Investasi</x-ui.button>
                @if($stop)<x-ui.button variant="secondary" wire:click="$set('closing', true)">Toko tutup</x-ui.button>@endif
            </div>
        </section>
    @endif

    @if($stop && $closing)
        <form wire:submit="saveClose" class="route-close-form">
            <x-ui.camera-photo wire-model="closedPhoto" label="Foto toko yang tutup" />
            @error('closedPhoto')<p class="text-danger" role="alert">{{ $message }}</p>@enderror
            <x-ui.input label="Catatan (opsional)" wire:model="closedNote" placeholder="Mis. buka lagi jam 4 sore" />
            <div class="stock-actions">
                <x-ui.button variant="secondary" wire:click="$set('closing', false)">Batal</x-ui.button>
                <x-ui.button type="submit" variant="primary">Simpan</x-ui.button>
            </div>
        </form>
    @endif

    <livewire:store-account :store-id="$buyer->id" :open="$akun" />

    <x-sales.section title="Nota terakhir">
        <div class="sales-list">
            @forelse($notes as $note)
                <x-sales.row as="button" type="button" x-data x-on:click="$dispatch('sheet-open', { sheet: 'nota-detail', for: {{ $note->id }} })" wire:key="note-{{ $note->id }}">
                    <b>{{ $note->number() }} · @rupiah($note->total)</b>
                    <x-slot:meta>{{ $note->created_at->wib()->format('d/m/Y H:i') }} · {{ $note->payment_method->label() }}</x-slot:meta>
                    <x-slot:trail>@if($note->proof_status)<x-ui.tag :variant="$note->proof_status->salesVariant()">{{ $note->proof_status->label() }}</x-ui.tag>@endif</x-slot:trail>
                </x-sales.row>
            @empty
                <x-ui.empty-state message="Belum ada nota untuk toko ini." />
            @endforelse
        </div>
    </x-sales.section>

    <x-sales.section title="Kunjungan terakhir">
        <div class="sales-list">
            @forelse($visits as $visit)
                <x-sales.row wire:key="visit-{{ $visit->id }}">
                    <b>{{ $visit->visited_at->wib()->format('d/m/Y H:i') }}</b>
                    <x-slot:meta>{{ $visit->notes ?? 'Tanpa catatan' }}</x-slot:meta>
                </x-sales.row>
            @empty
                <x-ui.empty-state message="Belum ada kunjungan." />
            @endforelse
        </div>
    </x-sales.section>
</x-sales.page>

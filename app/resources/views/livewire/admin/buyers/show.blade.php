<div class="adm-page store-report">
    <x-admin.page-header :title="$buyer->name">
        {{ $profile->owner_name ?: 'Pemilik belum diisi' }}
        · Sales {{ $profile->salesUser?->name ?? 'belum ada' }}
        @if($profile->area) · {{ $profile->area->name }} @endif
        @if($profile->phone) · {{ $profile->phone }} @endif
        · Kunjungan terakhir {{ $lastVisit ? $lastVisit->wib()->locale('id')->translatedFormat('j M Y, H.i') : '—' }}
        <x-slot:actions>
            <x-admin.pill :tone="$buyer->hasAccount() ? 'ok' : 'muted'">{{ $buyer->hasAccount() ? 'Sudah punya akun' : 'Belum punya akun' }}</x-admin.pill>
            <x-ui.button variant="secondary" wire:click="showStock"><x-lucide-package class="ico" />Cek stok</x-ui.button>
            <x-ui.button variant="secondary" tag="a" href="{{ route('admin.buyers.edit', $buyer) }}"><x-lucide-pencil class="ico" />Edit</x-ui.button>
        </x-slot:actions>
    </x-admin.page-header>

    @include('livewire.admin.reports.partials.store-period')

    <div class="adm-kpis store-kpis">
        @include('livewire.admin.reports.partials.store-figures')
        <x-admin.kpi :label="'Poin ('.$toysLine->label().')'" :value="number_format($profile->loyalty_points, 0, ',', '.')"
            :note="$progress['pcs'].' / '.$progress['per'].' pcs menuju poin berikutnya'" :progress="(int) round($progress['pcs'] / $progress['per'] * 100)" />
    </div>
    @unless($buyer->hasAccount())
        <p class="text-muted store-report-note">Poin tersimpan dan langsung bisa dipakai saat akun toko dibuat.</p>
    @endunless

    <x-admin.panel title="Per barang">
        @include('livewire.admin.reports.partials.store-items')
    </x-admin.panel>

    {{-- Cek stok: wire:ignore.self so Livewire's morph never closes it; its contents still update --}}
    <dialog class="adm-dialog store-stock-dialog" wire:ignore.self x-data x-on:store-stock-open.window="$el.showModal()" x-on:click.self="$el.close()">
        <div class="adm-dialog-head">
            <h2>Stok di toko · {{ $buyer->name }}</h2>
            <button type="button" class="adm-icon-btn" aria-label="Tutup" x-on:click="$el.closest('dialog').close()"><x-lucide-x width="18" height="18" /></button>
        </div>
        @include('livewire.admin.buyers.partials.stock', ['stock' => $stock])
    </dialog>
</div>

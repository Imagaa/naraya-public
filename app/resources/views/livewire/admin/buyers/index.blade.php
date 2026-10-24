<div class="adm-page">
    <x-admin.page-header title="Toko">
        {{ $counts[''] }} toko terdaftar
        <x-slot:actions><x-ui.button variant="secondary" tag="a" href="{{ route('admin.reports.toko') }}">Rekap semua toko</x-ui.button><x-ui.button variant="primary" tag="a" href="{{ route('admin.buyers.create') }}">Tambah toko</x-ui.button></x-slot:actions>
    </x-admin.page-header>

    <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama toko…" />
    <x-admin.queue-chip :label="$queueLabel" />
    <x-admin.filter-pills :options="['' => ['Semua', $counts['']], 'pending' => ['Menunggu persetujuan', $counts['pending']], 'rejected' => ['Ditolak', $counts['rejected']]]" :active="$status" action="setStatus" />

    <x-admin.list class="adm-list-topactions" cols="minmax(0,2.2fr) minmax(0,1.4fr) minmax(0,1.1fr) minmax(0,1fr) 120px 180px" :headers="['Toko', 'Sales', 'Telepon', 'Area', 'Status', '']" :empty="$buyers->isEmpty()" empty-message="Belum ada toko.">
        @foreach($buyers as $buyer)
            @php($p = $buyer->buyerProfile)
            <x-admin.list-row wire:key="b-{{ $buyer->id }}">
                <span class="adm-cell-main adm-with-avatar">
                    <x-ui.store-avatar :buyer="$buyer" />
                    <span><a href="{{ route('admin.buyers.show', $buyer) }}" class="adm-row-link">{{ $buyer->name }}</a>
                        <span class="adm-cell-sub">{{ $p?->owner_name ?? '' }}@unless($p?->hasCompleteStoreData()) · Data toko belum lengkap @endunless</span>
                    </span>
                </span>
                <span>@if($p?->salesUser)<span class="adm-with-avatar"><x-ui.person-avatar :user="$p->salesUser" :size="24" />{{ $p->salesUser->name }}</span>@else<span class="adm-muted">Belum ada</span>@endif</span>
                <span class="adm-cell-desk adm-num">{{ $p?->phone ?? '—' }}</span>
                <span class="adm-cell-desk">{{ $p?->area?->name ?? '—' }}</span>
                <span>
                    @if($p && $p->status !== \App\Enums\BuyerStatus::Approved)
                        <x-admin.pill :tone="$p->status === \App\Enums\BuyerStatus::Pending ? 'new' : 'bad'">{{ $p->status->label() }}</x-admin.pill>
                    @else
                        <x-admin.pill :tone="$p?->is_active ? 'ok' : 'muted'">{{ $p?->is_active ? 'Aktif' : 'Nonaktif' }}</x-admin.pill>
                    @endif
                </span>
                <span class="adm-cell-actions">
                    <x-ui.button variant="secondary" tag="a" href="{{ route('admin.buyers.edit', $buyer) }}" aria-label="Edit {{ $buyer->name }}"><x-lucide-pencil class="ico" /><span class="adm-btn-text">Edit</span></x-ui.button>
                    <x-ui.button variant="secondary" wire:click="showStock({{ $buyer->id }})" aria-label="Cek stok {{ $buyer->name }}"><x-lucide-package class="ico" /><span class="adm-btn-text">Cek stok</span></x-ui.button>
                </span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>

    {{ $buyers->links() }}

    {{-- Cek stok toko: wire:ignore.self so Livewire's morph never closes it; its contents still update --}}
    <dialog class="adm-dialog store-stock-dialog" wire:ignore.self x-data x-on:store-stock-open.window="$el.showModal()" x-on:click.self="$el.close()">
        <div class="adm-dialog-head">
            <h2>Stok di toko{{ $stockStore ? ' · '.$stockStore->name : '' }}</h2>
            <button type="button" class="adm-icon-btn" aria-label="Tutup" x-on:click="$el.closest('dialog').close()"><x-lucide-x width="18" height="18" /></button>
        </div>
        @if($stockStore)
            @include('livewire.admin.buyers.partials.stock', ['stock' => $stock])
        @endif
    </dialog>
</div>

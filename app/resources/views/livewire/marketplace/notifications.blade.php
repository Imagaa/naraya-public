<div class="page">
    <x-mp.header />

    <main class="container pg">
        <div class="pg-head">
            <h1>Notifikasi</h1>
            <p>Kabar terbaru soal pesanan, komplain, hadiah dan stok produk.</p>
        </div>

        @if($unread > 0)
            <div class="ntf-tools">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="markAllNotificationsRead"><x-lucide-check-check class="ico" />Tandai semua dibaca</button>
            </div>
        @endif

        @forelse($groups as $label => $items)
            <section aria-labelledby="ntf-h-{{ $loop->index }}">
                <h2 class="ntf-group" id="ntf-h-{{ $loop->index }}">{{ $label }}</h2>
                <div class="panel ntf-list">
                    @foreach($items as $notification)
                        <x-mp.notification-item :notification="$notification" wire:key="ntf-{{ $notification->id }}" />
                    @endforeach
                </div>
            </section>
        @empty
            <section class="panel">
                <x-mp.empty icon="bell" title="Belum ada notifikasi." />
            </section>
        @endforelse

        {{ $notifications->links('components.mp.pagination') }}
    </main>

</div>

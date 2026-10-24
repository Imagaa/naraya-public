<div class="adm-page">
    <x-admin.page-header title="Notifikasi">
        <x-slot:actions>
            @if($unread > 0)
                <x-ui.button variant="secondary" wire:click="markAllNotificationsRead">Tandai semua dibaca</x-ui.button>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="adm-push" wire:ignore>
        <div>
            <strong>Notifikasi di perangkat ini</strong>
            <p>Push hanya untuk yang mendesak: review pembayaran, komplain, setoran disengketakan, stok habis.</p>
            <p id="pwa-push-state"></p>
        </div>
        <button type="button" id="pwa-push" class="btn btn-primary" hidden>Aktifkan notifikasi di perangkat ini</button>
        <x-ui.button variant="secondary" wire:click="sendTest">Kirim notifikasi uji</x-ui.button>
        <a href="{{ route('admin.install') }}" class="adm-more">Pasang aplikasi admin di HP</a>
    </div>

    <div class="adm-ntf-list">
        @forelse($notifications as $notification)
            <x-admin.notification-item :notification="$notification" />
        @empty
            <x-ui.empty-state message="Belum ada notifikasi." />
        @endforelse
    </div>

    {{ $notifications->links() }}
</div>

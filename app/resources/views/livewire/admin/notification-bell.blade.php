<div class="adm-bell" wire:poll.30s>
    <button type="button" class="adm-icon-btn" popovertarget="adm-bell-panel" aria-label="Notifikasi{{ $unread ? ', '.$unread.' belum dibaca' : '' }}">
        <x-lucide-bell width="18" height="18" />
        @if($unread > 0)<span class="adm-badge">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
    </button>
    <div id="adm-bell-panel" popover class="adm-bell-panel">
        <div class="adm-bell-head">
            <strong>Notifikasi</strong>
            @if($unread > 0)<button type="button" wire:click="markAllNotificationsRead">Tandai semua dibaca</button>@endif
        </div>
        @forelse($latest as $notification)
            <x-admin.notification-item :notification="$notification" />
        @empty
            <p class="adm-bell-empty">Belum ada notifikasi.</p>
        @endforelse
        <a class="adm-bell-all" href="{{ route('admin.notifications') }}">Lihat semua</a>
    </div>
</div>

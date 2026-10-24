{{-- Polls every 15s; Livewire skips most polls in a hidden tab, so refresh the moment the tab is visible again. --}}
<div class="bell" wire:poll.15s x-on:visibilitychange.document="document.hidden || $wire.$refresh()" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    <a href="{{ route('notifications') }}" class="act" aria-label="Notifikasi{{ $unread ? ', '.$unread.' belum dibaca' : '' }}" x-bind:aria-expanded="open"
       x-on:click="if (window.matchMedia('(min-width: 640px)').matches) { $event.preventDefault(); open = ! open }">
        <x-lucide-bell class="ico" />
        @if($unread > 0)
            <span class="bell-badge num">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </a>
    <div class="bell-panel" x-show="open" x-cloak x-transition.opacity>
        <div class="bell-head">
            <b>Notifikasi</b>
            @if($unread > 0)
                <button type="button" class="btn btn-secondary btn-sm" wire:click="markAllNotificationsRead">Tandai semua dibaca</button>
            @endif
        </div>
        <div class="bell-list">
            @forelse($latest as $notification)
                <x-mp.notification-item :notification="$notification" wire:key="bell-{{ $notification->id }}" />
            @empty
                <x-mp.empty icon="bell" title="Belum ada notifikasi." />
            @endforelse
        </div>
        <a class="bell-foot" href="{{ route('notifications') }}">Lihat semua</a>
    </div>
</div>

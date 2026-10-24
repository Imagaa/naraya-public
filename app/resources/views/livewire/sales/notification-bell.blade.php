{{-- Polls every 15s; Livewire skips most polls in a hidden tab, so refresh as soon as the tab is visible again. --}}
<a href="{{ route('sales.more', ['buka' => 'notifikasi']) }}" class="sales-bell" data-unread="{{ $unread }}"
   wire:poll.15s x-data x-on:visibilitychange.document="document.hidden || $wire.$refresh()"
   aria-label="Notifikasi{{ $unread ? ', '.$unread.' belum dibaca' : '' }}">
    <x-lucide-bell width="20" height="20" />
    @if($unread > 0)
        <span class="nav-badge">{{ $unread > 9 ? '9+' : $unread }}</span>
    @endif
</a>

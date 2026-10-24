@props(['notification'])
@php($d = $notification->data)
<button type="button" @class(['ntf', 'is-unread' => $notification->unread()]) wire:click="openNotification('{{ $notification->id }}')">
    <span @class(['ntf-ico', 'tone-'.($d['tone'] ?? 'info')])><x-dynamic-component :component="'lucide-'.($d['icon'] ?? 'bell')" class="ico" /></span>
    <span class="ntf-text">
        <b>{{ $d['title'] ?? '' }}</b>
        <span>{{ $d['body'] ?? '' }}</span>
        <small>{{ $notification->created_at->locale('id')->diffForHumans() }}</small>
    </span>
    @if($notification->unread())
        <span class="ntf-dot" role="img" aria-label="Belum dibaca"></span>
    @endif
</button>

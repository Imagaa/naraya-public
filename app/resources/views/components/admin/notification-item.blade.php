@props(['notification'])
@php($d = $notification->data)

<button type="button" wire:key="ntf-{{ $notification->id }}" wire:click="openNotification('{{ $notification->id }}')" @class(['adm-ntf', 'is-unread' => $notification->unread()])>
    <span class="adm-ntf-icon tone-{{ $d['tone'] ?? 'info' }}"><x-dynamic-component :component="'lucide-'.($d['icon'] ?? 'bell')" width="16" height="16" /></span>
    <span class="adm-ntf-text">
        <b>{{ $d['title'] ?? '' }}</b>
        <span>{{ $d['body'] ?? '' }}</span>
        <small>{{ $notification->created_at->locale('id')->diffForHumans() }}</small>
    </span>
</button>

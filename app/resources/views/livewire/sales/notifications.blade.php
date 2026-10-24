<x-sales.page title="Notifikasi" :back="route('sales.more')">
    <x-slot:actions>
        @if($unread > 0)
            <x-ui.button variant="secondary" wire:click="markAllNotificationsRead">Tandai dibaca</x-ui.button>
        @endif
    </x-slot:actions>

    <div class="sales-list">
        @forelse($notifications as $notification)
            @php($d = $notification->data)
            <x-sales.row as="button" type="button" wire:key="ntf-{{ $notification->id }}" wire:click="openNotification('{{ $notification->id }}')" :class="$notification->unread() ? 'is-unread' : ''">
                <x-slot:lead><span class="sales-row-icon tone-{{ $d['tone'] ?? 'info' }}"><x-dynamic-component :component="'lucide-'.($d['icon'] ?? 'bell')" width="16" height="16" /></span></x-slot:lead>
                <b>{{ $d['title'] ?? '' }}</b>
                <span>{{ $d['body'] ?? '' }}</span>
                <x-slot:meta>{{ $notification->created_at->locale('id')->diffForHumans() }}</x-slot:meta>
                <x-slot:trail>
                    @if($notification->unread())
                        <span class="sales-dot" role="img" aria-label="Belum dibaca"></span>
                    @endif
                </x-slot:trail>
            </x-sales.row>
        @empty
            <x-ui.empty-state message="Belum ada notifikasi." />
        @endforelse
    </div>

    {{ $notifications->links() }}
</x-sales.page>

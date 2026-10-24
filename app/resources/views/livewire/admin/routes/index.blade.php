<div class="adm-page">
    <x-admin.page-header title="Rute sales">Jalur kunjungan yang dibuat sales, terbaru di atas.</x-admin.page-header>

    <div class="adm-toolbar">
        <select class="input" wire:model.live="salesId" aria-label="Sales">
            <option value="">Semua sales</option>
            @foreach($salesPeople as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
        </select>
    </div>

    <x-admin.list cols="120px minmax(0,1.5fr) 90px 110px 90px 90px 130px" :headers="['Tanggal', 'Sales', 'Toko', 'Dikunjungi', 'Tutup', 'Belum', 'Status']" :empty="$routes->isEmpty()" empty-message="Belum ada rute.">
        @foreach($routes as $r)
            <x-admin.list-row :href="route('admin.routes.index', array_filter(['sales' => $salesId, 'rute' => $r->id]))" wire:key="rt-{{ $r->id }}" :class="$selected?->is($r) ? 'is-active' : ''">
                <span class="adm-num">{{ $r->date->locale('id')->translatedFormat('j M Y') }}</span>
                <span class="adm-cell-main adm-with-avatar"><x-ui.person-avatar :user="$r->salesUser" :size="28" />{{ $r->salesUser?->name }}</span>
                <span class="adm-num">{{ $r->stops_count }}</span>
                <span class="adm-num">{{ $r->visited_count }}</span>
                <span class="adm-num">{{ $r->closed_count }}</span>
                <span class="adm-num">{{ $r->stops_count - $r->visited_count - $r->closed_count }}</span>
                <span><x-admin.pill :tone="$r->status === 'active' ? 'info' : 'muted'">{{ $r->status === 'active' ? 'Berjalan' : 'Selesai' }}</x-admin.pill></span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>
    {{ $routes->links() }}

    @if($selected)
        <x-admin.panel :title="'Rute '.$selected->salesUser?->name.' · '.$selected->date->locale('id')->translatedFormat('j M Y')" class="adm-phone-sheet">
            <x-slot:actions><x-ui.button variant="secondary" tag="a" href="{{ route('admin.routes.index', array_filter(['sales' => $salesId])) }}">Tutup</x-ui.button></x-slot:actions>
            <x-admin.list cols="40px minmax(0,1.6fr) 140px minmax(0,2fr)" :headers="['#', 'Toko', 'Status', 'Nota / catatan']">
                @foreach($selected->stops as $stop)
                    <x-admin.list-row wire:key="rs-{{ $stop->id }}">
                        <span class="adm-num">{{ $stop->position }}</span>
                        <span class="adm-cell-main"><a href="{{ route('admin.buyers.edit', $stop->buyer_id) }}">{{ $stop->buyer?->name }}</a></span>
                        <span><x-admin.pill :tone="['visited' => 'ok', 'closed' => 'bad'][$stop->status] ?? 'muted'">{{ ['visited' => 'Dikunjungi', 'closed' => 'Toko tutup'][$stop->status] ?? 'Belum' }}</x-admin.pill></span>
                        <span>
                            @foreach($stop->notes as $note)<a href="{{ route('admin.notes.show', $note) }}">{{ $note->number() }}</a> · Rp {{ number_format($note->total, 0, ',', '.') }} @endforeach
                            @if($stop->status === 'closed')
                                {{ $stop->closed_note }}
                                @if($stop->closedPhotoUrl())<a href="{{ $stop->closedPhotoUrl() }}" target="_blank" rel="noopener">Foto</a>@endif
                            @endif
                        </span>
                    </x-admin.list-row>
                @endforeach
            </x-admin.list>
        </x-admin.panel>
    @endif
</div>

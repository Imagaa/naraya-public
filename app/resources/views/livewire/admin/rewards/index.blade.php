<div class="adm-page">
    <x-admin.page-header title="Katalog Hadiah">
        {{ $counts['active'] }} hadiah aktif
        <x-slot:actions><x-ui.button variant="primary" tag="a" href="{{ route('admin.rewards.create') }}">Tambah hadiah</x-ui.button></x-slot:actions>
    </x-admin.page-header>
    <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari hadiah…" />
    <x-admin.filter-pills :options="['all' => ['Semua', $counts['all']], 'active' => ['Aktif', $counts['active']], 'inactive' => ['Nonaktif', $counts['inactive']]]" :active="$filter" action="setFilter" />
    <x-admin.list cols="56px minmax(0,2fr) 90px 90px 120px" :headers="['', 'Nama', 'Point', 'Stok', 'Status']" :empty="$rewards->isEmpty()" empty-message="Belum ada hadiah.">
        @foreach($rewards as $reward)
            <x-admin.list-row :href="route('admin.rewards.edit', $reward)">
                <span>
                    @if($reward->photo_path)
                        <img class="adm-thumb" src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($reward->photo_path) }}" alt="{{ $reward->name }}">
                    @else
                        —
                    @endif
                </span>
                <span class="adm-cell-main">{{ $reward->name }}</span>
                <span class="adm-num">{{ $reward->point_cost }}</span>
                <span class="adm-num">{{ $reward->stock }}</span>
                <span><x-admin.pill :tone="$reward->is_active ? 'ok' : 'muted'">{{ $reward->is_active ? 'Aktif' : 'Nonaktif' }}</x-admin.pill></span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>
    {{ $rewards->links() }}
</div>

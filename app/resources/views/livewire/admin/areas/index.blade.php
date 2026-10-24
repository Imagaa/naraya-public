<div class="adm-page" style="max-width:1180px">
    <x-admin.page-header title="Area sales">
        Dibuat otomatis: satu area per sales, lingkarannya dari titik toko sales itu. Warna pin dan lingkaran = sales.
        <x-slot:actions><x-ui.button variant="secondary" tag="a" href="{{ route('admin.sales.index') }}">Kembali ke Sales</x-ui.button></x-slot:actions>
    </x-admin.page-header>

    @if($groups->isNotEmpty())
        <div class="area-map-wrap">
            <div wire:ignore x-data="salesAreasMap(@js($groups))"><div x-ref="mapEl" class="area-map"></div></div>
            <ul class="area-legend">
                @foreach($groups as $g)
                    <li><i style="background: {{ $g['color'] }}"></i><b>{{ $g['name'] }}</b><span>{{ count($g['stores']) }} toko{{ $g['radiusKm'] ? ' · '.$g['radiusKm'].' km' : '' }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-admin.list cols="minmax(0,2fr) minmax(0,1.4fr) 90px 110px" :headers="['Area', 'Sales', 'Toko', 'Radius']" :empty="$areas->isEmpty()" empty-message="Belum ada area — area muncul setelah sales punya toko dengan titik.">
        @foreach($areas as $i => $area)
            <x-admin.list-row>
                <span class="adm-cell-main adm-with-avatar"><i class="area-swatch" style="background: {{ \App\Livewire\Admin\Areas\Index::COLORS[$i % count(\App\Livewire\Admin\Areas\Index::COLORS)] }}"></i>{{ $area->name }}</span>
                <span>{{ $area->salesProfiles->pluck('user.name')->filter()->first() ?: '—' }}</span>
                <span class="adm-num">{{ $area->buyer_profiles_count }}</span>
                <span class="adm-num">{{ $area->radius_km ? $area->radius_km.' km' : '—' }}</span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>
</div>

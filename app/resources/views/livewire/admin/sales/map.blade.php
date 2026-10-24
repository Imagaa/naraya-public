<div style="display:flex;flex-direction:column;gap:20px">
    <div class="adm-map-head" style="display:flex;align-items:center;justify-content:space-between">
        <h1 style="margin:0;font-size:24px">Peta Persebaran Sales</h1>
        <x-ui.button variant="secondary" tag="a" href="{{ route('admin.sales.index') }}">← Kembali</x-ui.button>
    </div>

    <x-ui.card style="max-width:900px">
        <div style="display:flex;gap:16px;align-items:center;font-size:13px;margin-bottom:12px">
            <span style="display:flex;align-items:center;gap:6px"><span style="width:12px;height:12px;border-radius:50%;background:#16A34A;display:inline-block"></span> Area kosong</span>
            <span style="display:flex;align-items:center;gap:6px"><span style="width:12px;height:12px;border-radius:50%;background:#C1603A;display:inline-block"></span> Sudah ada sales aktif</span>
        </div>

        <x-map.area-circles :areas="$areas" :label="null" height="600px" />
    </x-ui.card>

    <x-ui.table :headers="['Area', 'Radius', 'Sales Aktif']">
        @foreach($areas as $area)
            <tr>
                <td>{{ $area->name }}</td>
                <td>{{ $area->radius_km ? "{$area->radius_km} km" : '—' }}</td>
                <td>{{ $area->salesProfiles->pluck('user.name')->join(', ') ?: '—' }}</td>
            </tr>
        @endforeach
    </x-ui.table>
</div>

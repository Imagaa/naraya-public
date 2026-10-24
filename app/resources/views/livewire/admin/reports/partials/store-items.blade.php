{{-- Per item table with its Total row (StoreReport::items). --}}
@php($rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))
@if($items->isEmpty())
    <p class="text-muted store-report-empty">Belum ada penjualan di periode ini.</p>
@else
    <table class="store-stock-table store-report-table">
        <thead><tr><th>Barang</th><th class="adm-num">Terjual</th><th class="adm-num">Uang</th><th class="adm-num">Masih tertitip</th><th class="adm-num">Dibawa kembali</th></tr></thead>
        <tbody>
            @foreach($items as $r)
                <tr wire:key="item-{{ $r->product->id }}">
                    <td>{{ $r->product->name }}</td>
                    <td class="adm-num">{{ $r->product->qtyLabel($r->terjual) }}</td>
                    <td class="adm-num">{{ $rp($r->uang) }}</td>
                    <td class="adm-num">{{ $r->product->qtyLabel($r->masih_tertitip) }}</td>
                    <td class="adm-num">{{ $r->product->qtyLabel($r->dibawa_kembali) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="adm-num"><b>{{ number_format($items->sum('terjual'), 0, ',', '.') }} pcs</b></td>
                <td class="adm-num"><b>{{ $rp($items->sum('uang')) }}</b></td>
                <td class="adm-num"><b>{{ number_format($items->sum('masih_tertitip'), 0, ',', '.') }} pcs</b></td>
                <td class="adm-num"><b>{{ number_format($items->sum('dibawa_kembali'), 0, ',', '.') }} pcs</b></td>
            </tr>
        </tfoot>
    </table>
@endif

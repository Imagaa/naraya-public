{{-- Our goods at a store now and what they are worth (App\Support\Sales\Consignments::valuation): admin's "Cek stok toko". --}}
@php($rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))
@forelse($stock as $s)
    <div class="store-stock-line">
        <p class="store-stock-head"><b>{{ $s['line']->label() }}</b>@if($s['last']) · kunjungan terakhir {{ $s['last']->wib()->locale('id')->translatedFormat('j M Y, H.i') }}@endif</p>
        <table class="store-stock-table">
            <thead><tr><th>Barang</th><th>Di toko</th><th>Setor ke sales</th><th>Setor ke admin</th></tr></thead>
            <tbody>
                @foreach($s['rows'] as $r)
                    <tr><td>{{ $r['name'] }}</td><td class="adm-num">{{ $r['qty'] }}<small> ({{ number_format($r['pcs'], 0, ',', '.') }} pcs)</small></td><td class="adm-num">{{ $rp($r['sales']) }}</td><td class="adm-num">{{ $rp($r['admin']) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="2">Total</td><td class="adm-num"><b>{{ $rp($s['sales']) }}</b></td><td class="adm-num"><b>{{ $rp($s['admin']) }}</b></td></tr></tfoot>
        </table>
    </div>
@empty
    <p class="text-muted" style="margin:0">Belum ada barang titipan di toko ini.</p>
@endforelse
<p class="text-muted store-stock-note">Setor ke sales = yang dibayar toko bila semuanya terjual (harga jual). Setor ke admin = yang disetor sales (harga setoran sales).</p>

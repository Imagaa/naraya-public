{{-- Cart totals shared by every checkout step. Orders earn no points (points come from toys sold, 2026-10-23). --}}
<div class="sum num">
    <div class="sum-row"><span>Harga normal ({{ $unitCount }} barang)</span><span>@rupiah($grossTotal)</span></div>
    @if($savings > 0)
        <div class="sum-row save"><span>Hemat dari promo</span><span>−@rupiah($savings)</span></div>
    @endif
    @if($creditApplied > 0)
        <div class="sum-row"><span>Saldo kredit dipakai</span><span>−@rupiah($creditApplied)</span></div>
    @endif
    <div class="sum-row"><span>Ongkos kirim</span><span>Gratis</span></div>
    <div class="sum-row total"><span>Total</span><b>@rupiah($grandTotal)</b></div>
</div>

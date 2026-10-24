{{-- Sticky cart summary on catalogue pages; nothing when the cart is empty. --}}
@props(['count' => 0, 'total' => 0])
@if($count > 0 && config('naraya.marketplace_purchasing_open'))
    <a class="cart-bar" href="{{ route('checkout') }}">
        <span class="cart-bar-ico"><x-lucide-shopping-cart class="ico" /></span>
        <span class="cart-bar-text num"><b>{{ $count }} barang</b> · @rupiah($total)</span>
        <span class="btn btn-primary btn-sm">Lihat keranjang</span>
    </a>
@endif

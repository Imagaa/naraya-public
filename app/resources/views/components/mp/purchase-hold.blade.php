{{-- While online buying is on hold (config naraya.marketplace_purchasing_open): the same wording as the old closed-marketplace page, as a slim band. --}}
@unless(config('naraya.marketplace_purchasing_open'))
    <div class="mp-hold" role="status">
        <p class="container"><x-lucide-info class="ico" /><span><b>Pemesanan online ditutup sementara.</b> Naraya sedang menyiapkan layanan kulakan untuk toko. Sementara ini pesanan dilayani langsung oleh sales Naraya yang mengunjungi toko Anda.</span></p>
    </div>
@endunless

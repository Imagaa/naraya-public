<footer class="foot">
    <div class="foot-inner">
        <div>
            <div class="logo"><x-brand.logo tone="reversed" class="logo-img" /></div>
            <p class="foot-about">Distributor untuk warung dan toko, diantar langsung oleh sales area Anda.</p>
        </div>
        <div>
            <h4>Belanja</h4>
            <ul>
                <li><a href="{{ route('products.index') }}">Semua produk</a></li>
                <li><a href="{{ route('rewards.index') }}">Tukar poin</a></li>
            </ul>
        </div>
        <div>
            <h4>Akun</h4>
            <ul>
                <li><a href="{{ route('orders.index') }}">Pesanan saya</a></li>
                <li><a href="{{ route('account', 'profil') }}">Profil toko</a></li>
            </ul>
        </div>
        <div>
            <h4>Pembayaran</h4>
            <div class="pay"><span>Tunai</span><span>Transfer</span><span>QRIS</span><span>GoPay</span></div>
        </div>
    </div>
    <p class="foot-copy">© {{ now()->year }} Naraya · Foto &amp; data produk: <a href="https://world.openfoodfacts.org" target="_blank" rel="noopener">Open Food Facts</a> (CC BY-SA)</p>
</footer>

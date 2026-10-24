<div class="page">
    <x-mp.header title="Komplain" :subtitle="'Pesanan #'.$order->id" :back="route('orders.show', $order)" :tabbar="false" />

    <main class="container pg">
        <nav class="crumbs" aria-label="Lokasi">
            <a href="{{ route('orders.index') }}">Pesanan</a><x-lucide-chevron-right class="ico" />
            <a href="{{ route('orders.show', $order) }}" class="num">#{{ $order->id }}</a><x-lucide-chevron-right class="ico" />
            <span>Komplain</span>
        </nav>

        <div class="two">
            <form class="panel" wire:submit="submit" aria-labelledby="h-cmp">
                <div class="pg-head">
                    <h1 id="h-cmp">Ajukan komplain</h1>
                    <p>Ceritakan masalahnya dan lampirkan foto. Admin akan meninjau dan menghubungi Anda.</p>
                </div>

                <div class="field">
                    <label for="alasan">Apa masalahnya?</label>
                    <textarea id="alasan" class="input" wire:model="alasan" placeholder="Contoh: 2 botol Aqua bocor, 1 dus Indomie penyok"></textarea>
                    <span class="hint">Sebutkan produk dan jumlahnya supaya cepat diproses.</span>
                    @error('alasan')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="field">
                    <span class="field-label">Foto bukti</span>
                    <x-ui.file-picker id="foto" capture="environment" wire:model="foto" :file="$foto" label="Ambil atau pilih foto" hint="JPG atau PNG, maksimal 5 MB" />
                    @error('foto')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="btn btn-primary btn-block" wire:loading.attr="disabled">Kirim Komplain</button>
            </form>

            <aside class="stack">
                <section class="panel" aria-labelledby="h-cord">
                    <div class="panel-head">
                        <h2 id="h-cord" class="num">Pesanan #{{ $order->id }}</h2>
                        <x-mp.status :tone="$progress['tone']" :icon="$progress['icon']">{{ $progress['label'] }}</x-mp.status>
                    </div>
                    <div class="o-thumbs">
                        @foreach($order->items->take(3) as $item)
                            <div class="ph" style="--c: {{ $item->product?->category?->color ?? '#6B7480' }}">
                                @if($item->product)<x-mp.product-photo :product="$item->product" />@else<x-lucide-package class="ph-icon" />@endif
                            </div>
                        @endforeach
                        @if($order->items->count() > 3)<span class="more num">+{{ $order->items->count() - 3 }}</span>@endif
                    </div>
                    <dl class="kv num">
                        <div><dt>Tanggal</dt><dd>{{ $order->created_at->timezone(config('app.business_timezone'))->locale('id')->translatedFormat('j M Y') }}</dd></div>
                        <div><dt>Total</dt><dd>@rupiah($order->total)</dd></div>
                    </dl>
                </section>
                <p class="note ok"><x-lucide-wallet class="ico" /><span>Jika komplain disetujui, penggantian masuk sebagai <b>saldo kredit</b> dan otomatis dipotong di belanja berikutnya.</span></p>
            </aside>
        </div>
    </main>
</div>

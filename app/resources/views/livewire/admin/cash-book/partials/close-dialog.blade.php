@if($canClose)
    <dialog id="cash-close" class="adm-dialog" wire:ignore.self x-data x-on:cash-close.window="$event.detail.name === 'close' && $el.close()">
        <h2>Tutup buku {{ $start->locale('id')->translatedFormat('F Y') }}?</h2>
        <div class="adm-kv"><span>Saldo awal</span><strong>Rp {{ number_format($summary['opening'], 0, ',', '.') }}</strong></div>
        <div class="adm-kv"><span>Masuk</span><strong>Rp {{ number_format($summary['in'], 0, ',', '.') }}</strong></div>
        <div class="adm-kv"><span>Keluar</span><strong>Rp {{ number_format($summary['out'], 0, ',', '.') }}</strong></div>
        <div class="adm-kv adm-total"><span>Saldo akhir</span><strong>Rp {{ number_format($summary['closing'], 0, ',', '.') }}</strong></div>
        <p class="adm-muted">Setelah ditutup, transaksi bulan ini tidak bisa ditambah atau diubah. Koreksi dicatat di bulan berjalan.</p>
        <div class="adm-dialog-actions">
            <button type="button" class="btn btn-secondary" @click="$el.closest('dialog').close()">Batal</button>
            <button type="button" class="btn btn-primary" wire:click="closeMonth">Tutup buku</button>
        </div>
    </dialog>
@endif

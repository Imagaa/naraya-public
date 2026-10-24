<dialog id="cash-correction" class="adm-dialog" wire:ignore.self x-data
        x-on:cash-open.window="$event.detail.name === 'correction' && $el.showModal()"
        x-on:cash-close.window="$event.detail.name === 'correction' && $el.close()">
    <form wire:submit="saveCorrection">
        <h2>Koreksi transaksi</h2>
        @if($correcting)
            <p class="adm-muted">{{ $correcting->date->locale('id')->translatedFormat('j M Y') }} · {{ $correcting->catatan }} · Rp {{ number_format($correcting->amount, 0, ',', '.') }}</p>
        @endif
        <label class="adm-radio"><input type="radio" value="full" wire:model.live="correctionMode"> Batalkan seluruhnya</label>
        <label class="adm-radio"><input type="radio" value="amount" wire:model.live="correctionMode"> Ubah nominal menjadi</label>
        @if($correctionMode === 'amount')
            <div wire:key="corr-amount-{{ $correctingId }}"><x-admin.currency-input label="Nominal yang benar" wire-model="correctionAmount" :value="$correctionAmount" /></div>
            @error('correctionAmount')<p class="text-danger">{{ $message }}</p>@enderror
        @endif
        <label class="field">Alasan <textarea class="input" rows="2" wire:model="correctionReason"></textarea></label>
        @error('correctionReason')<p class="text-danger">{{ $message }}</p>@enderror
        <p class="adm-muted">Koreksi dicatat sebagai transaksi baru hari ini; transaksi asli tidak berubah.</p>
        <div class="adm-dialog-actions">
            <button type="button" class="btn btn-secondary" @click="$el.closest('dialog').close()">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan koreksi</button>
        </div>
    </form>
</dialog>

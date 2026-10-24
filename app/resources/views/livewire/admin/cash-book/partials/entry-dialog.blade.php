<dialog id="cash-entry" class="adm-dialog" wire:ignore.self x-data
        x-on:cash-open.window="$event.detail.name === 'entry' && $el.showModal()"
        x-on:cash-close.window="$event.detail.name === 'entry' && $el.close()">
    @if($step === 'form')
        <form wire:submit="review">
            <h2>{{ $editingId ? 'Ubah transaksi' : 'Catat transaksi' }}</h2>
            <div class="adm-seg" role="radiogroup" aria-label="Jenis">
                <label><input type="radio" value="pemasukan" wire:model.live="formType"> Pemasukan</label>
                <label><input type="radio" value="pengeluaran" wire:model.live="formType"> Pengeluaran</label>
            </div>
            <div wire:key="amount-{{ $formKey }}"><x-admin.currency-input label="Nominal" wire-model="formAmount" :value="$formAmount" /></div>
            @error('formAmount')<p class="text-danger">{{ $message }}</p>@enderror
            <label class="field">Tanggal <input type="date" class="input" wire:model="formDate" max="{{ \App\Support\BusinessDay::today()->toDateString() }}"></label>
            @error('formDate')<p class="text-danger">{{ $message }}</p>@enderror
            @if($formType === 'pengeluaran')
                <label class="field">Kategori
                    <select class="input" wire:model="formCategoryId">
                        <option value="">— Pilih kategori —</option>
                        @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </label>
                @error('formCategoryId')<p class="text-danger">{{ $message }}</p>@enderror
            @endif
            <label class="field">Keterangan <textarea class="input" rows="2" wire:model="formNote"></textarea></label>
            @error('formNote')<p class="text-danger">{{ $message }}</p>@enderror
            <div class="field"><span>Foto bukti (opsional)</span><x-ui.file-picker wire:model="formPhoto" :file="$formPhoto" label="Pilih foto bukti" hint="Struk, nota atau bukti transfer" /></div>
            @error('formPhoto')<p class="text-danger">{{ $message }}</p>@enderror
            <div class="adm-dialog-actions">
                <button type="button" class="btn btn-secondary" @click="$el.closest('dialog').close()">Batal</button>
                <button type="submit" class="btn btn-primary">Periksa</button>
            </div>
        </form>
    @else
        <form wire:submit="save">
            <h2>Periksa sebelum menyimpan</h2>
            <p class="adm-confirm-amount {{ $formType === 'pemasukan' ? 'is-in' : 'is-out' }}">
                {{ $formType === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' }} <b>Rp {{ number_format((int) $formAmount, 0, ',', '.') }}</b>
            </p>
            <p>{{ \Illuminate\Support\Carbon::parse($formDate)->locale('id')->translatedFormat('l, j F Y') }}@if($formType === 'pengeluaran' && $formCategoryId) · {{ $categories->firstWhere('id', $formCategoryId)?->name }}@endif</p>
            <p>{{ $formNote }}</p>
            @if($formPhoto)<p class="adm-muted">Foto: {{ $formPhoto->getClientOriginalName() }}</p>@endif
            @if((int) $formAmount >= \App\Livewire\Admin\CashBook\Index::BIG_AMOUNT)
                <p class="adm-big-warning">Nominal besar — pastikan jumlah nol-nya benar.</p>
            @endif
            <p class="adm-muted">Setelah {{ \App\Models\CashBookEntry::EDIT_MINUTES }} menit transaksi tidak bisa diubah, hanya bisa dikoreksi.</p>
            <label class="adm-radio"><input type="checkbox" wire:model.live="confirmed"> Saya sudah memeriksa nominal dan tanggal</label>
            @error('confirmed')<p class="text-danger">{{ $message }}</p>@enderror
            @error('cash')<p class="text-danger">{{ $message }}</p>@enderror
            <div class="adm-dialog-actions">
                <button type="button" class="btn btn-secondary" wire:click="back">Kembali</button>
                <button type="submit" class="btn btn-primary" @disabled(! $confirmed)>Simpan</button>
            </div>
        </form>
    @endif
</dialog>

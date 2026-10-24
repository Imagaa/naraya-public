<div class="adm-page adm-settings">
    <x-admin.settings-tabs />
    <x-admin.page-header title="Buku Kas" />
    <x-admin.panel title="Tutup buku otomatis">
        <form wire:submit="save" class="adm-inline-form">
            <label class="field">Tanggal tutup buku setiap bulan
                <input type="number" min="1" max="28" class="input" wire:model="closingDay">
            </label>
            <x-ui.save-button>Simpan</x-ui.save-button>
        </form>
        @error('closingDay')<p class="text-danger">{{ $message }}</p>@enderror
        @if($saved)<p class="adm-flash" role="status"><x-lucide-check width="16" height="16" />Tersimpan.</p>@endif
        <p class="adm-muted">Pada tanggal ini, bulan sebelumnya ditutup otomatis. Admin tetap bisa menutup lebih awal dari halaman Buku Kas.</p>
    </x-admin.panel>
</div>

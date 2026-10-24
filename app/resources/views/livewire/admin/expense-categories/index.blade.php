<div class="adm-page adm-settings">
    <x-admin.settings-tabs />
    <x-admin.page-header title="Kategori Pengeluaran" />

    <form wire:submit="addCategory" class="adm-inline-form">
        <input type="text" class="input" wire:model="newCategoryName" placeholder="Nama kategori baru" aria-label="Nama kategori baru">
        <x-ui.save-button target="newCategoryName"><x-lucide-plus class="ico" />Tambah</x-ui.save-button>
    </form>
    @error('newCategoryName')<p class="text-danger">{{ $message }}</p>@enderror

    <x-admin.list cols="minmax(0,1fr) 110px 150px" :headers="['Nama', 'Status', '']" :empty="$categories->isEmpty()" empty-message="Belum ada kategori pengeluaran.">
        @foreach($categories as $category)
            <x-admin.list-row>
                <span class="adm-cell-main">{{ $category->name }}</span>
                <span><x-admin.pill :tone="$category->is_active ? 'ok' : 'muted'">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</x-admin.pill></span>
                <span class="adm-cell-actions"><x-ui.button variant="secondary" wire:click="toggleActive({{ $category->id }})">{{ $category->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</x-ui.button></span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>
</div>

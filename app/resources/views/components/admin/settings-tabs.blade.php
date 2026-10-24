{{-- The three settings pages stay separate; one shared tab row makes them read as one "Pengaturan". --}}
<x-admin.tabs :items="[
    'admin.loyalty-settings.edit' => 'Point',
    'admin.warehouse-settings.edit' => 'Gudang',
    'admin.cash-book-settings.edit' => 'Buku Kas',
    'admin.attendance-settings.edit' => 'Absensi',
    'admin.expense-categories.index' => 'Kategori Pengeluaran',
]" />

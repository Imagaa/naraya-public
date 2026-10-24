<div class="adm-page">
    <x-admin.page-header title="Pengambilan sales">Semua barang yang diambil, diretur, dan selisih cek stok sales di lini {{ $line->label() }}.</x-admin.page-header>

    <div class="adm-toolbar">
        <select class="input" wire:model.live="salesId" aria-label="Sales">
            <option value="">Semua sales</option>
            @foreach($salesPeople as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
        </select>
    </div>
    <x-admin.filter-pills :options="array_merge(['all' => ['Semua', $counts['all']]], collect($kinds)->mapWithKeys(fn ($k) => [$k->value => [$k->label(), $counts[$k->value]]])->all())" :active="$filter" action="setFilter" />

    <x-admin.list cols="118px minmax(0,1fr) 130px minmax(0,2.6fr) 110px 120px" :headers="['Tanggal', 'Sales', 'Jenis', 'Barang', 'Jumlah', 'Nilai']" :empty="$transfers->isEmpty()" empty-message="Belum ada catatan di filter ini.">
        @foreach($transfers as $t)
            <x-admin.list-row wire:key="tr-{{ $t->id }}">
                <span class="adm-num">{{ $t->created_at->wib()->locale('id')->translatedFormat('j M Y H.i') }}</span>
                <span class="adm-cell-main adm-with-avatar"><x-ui.person-avatar :user="$t->salesUser" :size="28" />{{ $t->salesUser?->name }}</span>
                <span><x-admin.pill :tone="$t->kind->tone()">{{ $t->kind->label() }}</x-admin.pill></span>
                <span class="adm-cell-wide"><x-ui.item-lines :items="$t->items->map(fn ($i) => [$i->product, $i->quantity])" />@if($t->note)<span class="adm-cell-sub">{{ $t->note }}</span>@endif</span>
                <span class="adm-num"><x-ui.qty-total :items="$t->items->map(fn ($i) => [$i->product, $i->quantity])" /></span>
                <span class="adm-num">Rp {{ number_format($t->totalValue(), 0, ',', '.') }}</span>
            </x-admin.list-row>
        @endforeach
    </x-admin.list>
    {{ $transfers->links() }}
</div>

<div class="adm-page">
    <x-admin.page-header title="Buku Kas">
        {{ $start->locale('id')->translatedFormat('F Y') }} ·
        @if($closing)
            Ditutup {{ $closing->closed_at->wib()->locale('id')->translatedFormat('j M Y') }} {{ $closing->auto ? 'otomatis' : 'oleh '.$closing->closedBy?->name }}
        @else
            Buka
        @endif
        <x-slot:actions>
            @unless($closing)
                <x-ui.button variant="primary" wire:click="openCreate">+ Catat transaksi</x-ui.button>
            @endunless
        </x-slot:actions>
    </x-admin.page-header>

    <div class="adm-month-nav">
        <a class="btn btn-secondary" href="{{ route('admin.cash-book.index', ['bulan' => $start->copy()->subMonth()->format('Y-m')]) }}">◀ {{ $start->copy()->subMonth()->locale('id')->translatedFormat('M') }}</a>
        <strong>{{ $start->locale('id')->translatedFormat('F Y') }}</strong>
        @unless($isCurrent)
            <a class="btn btn-secondary" href="{{ route('admin.cash-book.index', ['bulan' => $start->copy()->addMonth()->format('Y-m')]) }}">{{ $start->copy()->addMonth()->locale('id')->translatedFormat('M') }} ▶</a>
        @endunless
        @if($canClose)
            <button type="button" class="btn btn-secondary" x-data @click="document.getElementById('cash-close').showModal()">Tutup buku {{ $start->locale('id')->translatedFormat('F') }}</button>
        @endif
    </div>

    @error('cash')<div class="adm-alert"><p>{{ $message }}</p></div>@enderror

    <div class="adm-kpis">
        <x-admin.kpi label="Saldo awal" :value="'Rp '.number_format($summary['opening'], 0, ',', '.')" />
        <x-admin.kpi label="Masuk" :value="'Rp '.number_format($summary['in'], 0, ',', '.')" />
        <x-admin.kpi label="Keluar" :value="'Rp '.number_format($summary['out'], 0, ',', '.')" />
        <x-admin.kpi label="Saldo akhir" :value="'Rp '.number_format($summary['closing'], 0, ',', '.')" />
    </div>

    @if($isCurrent && $today)
        <x-admin.section title="Hari ini (belum dibukukan)">
            <div class="adm-today">
                @foreach($today as $t)
                    <span>{{ $t['source']->label() }} · {{ $t['count'] }} <b class="{{ $t['type']->value === 'pemasukan' ? 'is-in' : 'is-out' }}">{{ $t['type']->value === 'pemasukan' ? '+' : '−' }}Rp {{ number_format($t['amount'], 0, ',', '.') }}</b></span>
                @endforeach
            </div>
        </x-admin.section>
    @endif

    <div class="adm-toolbar">
        <x-ui.input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari keterangan…" />
        <select class="input" wire:model.live="categoryFilter" aria-label="Kategori">
            <option value="">Semua kategori</option>
            @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
        </select>
    </div>
    <x-admin.filter-pills :options="['all' => ['Semua', null], 'masuk' => ['Masuk', null], 'keluar' => ['Keluar', null], 'koreksi' => ['Koreksi', null]]" :active="$filter" action="setFilter" />

    <x-admin.list cols="84px minmax(0,2.6fr) minmax(0,1.2fr) 112px 112px 128px 170px" :headers="['Tgl', 'Keterangan', 'Kategori/Sumber', 'Masuk', 'Keluar', 'Saldo', '']" :empty="$rows->isEmpty()" empty-message="Belum ada transaksi di bulan ini.">
        @foreach($rows as ['entry' => $e, 'saldo' => $saldo])
            @php($in = $e->type === \App\Enums\CashBookEntryType::Pemasukan)
            <div x-data="{ open: false }" wire:key="cash-{{ $e->id }}">
                <x-admin.list-row>
                    <span class="adm-num">{{ $e->date->locale('id')->translatedFormat('j M') }}</span>
                    <span class="adm-cell-main">
                        @if($e->kind === \App\Enums\CashBookEntryKind::Rekap && $e->items->isNotEmpty())
                            <button type="button" class="adm-linkish" @click="open = !open">{{ $e->catatan }} <span x-text="open ? '▾' : '▸'"></span></button>
                        @elseif($e->legacySubjectUrl())
                            <a href="{{ $e->legacySubjectUrl() }}">{{ $e->catatan ?? $e->reference }}</a>
                        @else
                            {{ $e->catatan ?? $e->reference ?? '—' }}
                        @endif
                        @if($e->foto_path) <a href="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($e->foto_path) }}" target="_blank" rel="noopener" aria-label="Lihat bukti">📎</a>@endif
                        <span class="adm-cell-sub">
                            @if($e->kind === \App\Enums\CashBookEntryKind::Manual){{ $e->user?->name ?? 'Sistem' }}@endif
                            @if($e->kind === \App\Enums\CashBookEntryKind::Manual && $e->isWithinEditWindow() && $e->user_id === auth()->id())
                                · bisa diubah {{ (int) ceil(\App\Models\CashBookEntry::EDIT_MINUTES - $e->created_at->diffInMinutes(now())) }} menit lagi
                            @endif
                            @if($e->corrections->isNotEmpty()) · dikoreksi @endif
                        </span>
                    </span>
                    <span class="adm-cell-desk">
                        @if($e->kind === \App\Enums\CashBookEntryKind::Rekap)<x-admin.pill tone="info">Rekap otomatis</x-admin.pill>
                        @elseif($e->kind === \App\Enums\CashBookEntryKind::Koreksi)<x-admin.pill tone="bad">Koreksi</x-admin.pill>
                        @else{{ $e->expenseCategory?->name ?? 'Pemasukan' }}@endif
                    </span>
                    <span class="adm-num is-in">{{ $in ? number_format($e->amount, 0, ',', '.') : '' }}</span>
                    <span class="adm-num is-out">{{ $in ? '' : number_format($e->amount, 0, ',', '.') }}</span>
                    <span class="adm-num adm-cell-desk">{{ number_format($saldo, 0, ',', '.') }}</span>
                    <span class="adm-cell-actions">
                        @if($e->kind === \App\Enums\CashBookEntryKind::Manual && $e->isWithinEditWindow() && $e->user_id === auth()->id() && ! $closing)
                            <x-ui.button variant="secondary" wire:click="openEdit({{ $e->id }})">Ubah</x-ui.button>
                            <x-ui.button variant="secondary" wire:click="delete({{ $e->id }})" wire:confirm="Hapus transaksi ini?">Hapus</x-ui.button>
                        @elseif($e->kind !== \App\Enums\CashBookEntryKind::Koreksi)
                            <x-ui.button variant="secondary" wire:click="openCorrection({{ $e->id }})">Koreksi</x-ui.button>
                        @endif
                    </span>
                </x-admin.list-row>
                @if($e->kind === \App\Enums\CashBookEntryKind::Rekap && $e->items->isNotEmpty())
                    <div class="adm-list-extra" x-show="open" x-cloak>
                        @foreach($e->items as $item)
                            @php($s = $item->subject)
                            @if($s instanceof \App\Models\Order)
                                <a href="{{ route('admin.orders.show', $s) }}">Order #{{ $s->id }}</a>
                            @elseif($s instanceof \App\Models\SalesNote)
                                <a href="{{ route('admin.notes.show', $s) }}">Nota {{ $s->number() }}</a>
                            @else
                                <a href="{{ route('admin.deposits.show', $s) }}">Setoran #{{ $s?->id }}</a>
                            @endif
                            <span class="adm-num">Rp {{ number_format($item->amount, 0, ',', '.') }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </x-admin.list>

    @unless($closing)
        @include('livewire.admin.cash-book.partials.entry-dialog')
    @endunless
    @include('livewire.admin.cash-book.partials.correction-dialog')
    @include('livewire.admin.cash-book.partials.close-dialog')
</div>

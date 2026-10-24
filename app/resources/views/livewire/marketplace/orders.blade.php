<div class="page">
    <x-mp.header active="orders" />

    <main class="container pg">
        <div class="pg-head">
            <h1>Pesanan Saya</h1>
            <p>Lacak pesanan yang berjalan dan pesan ulang dari riwayat.</p>
        </div>

        @if($flash)
            <p class="note sale" role="status"><x-lucide-circle-alert class="ico" /><span>{{ $flash }}</span></p>
        @endif

        <div class="two">
            <div class="stack">
                <x-mp.hscroll class="filters" role="group" aria-label="Saring status" label="filter">
                    @foreach([
                        'all' => 'Semua',
                        'new' => 'Baru',
                        'processing' => 'Diproses',
                        'shipped' => 'Dikirim',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ] as $key => $label)
                        <button type="button" wire:click="setFilter('{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">
                            {{ $label }} <span class="num">{{ $statusCounts[$key] }}</span>
                        </button>
                    @endforeach
                </x-mp.hscroll>

                <div class="orders">
                    @forelse($orders as $order)
                        <x-mp.order-card :order="$order" />
                    @empty
                        <section class="panel">
                            <x-mp.empty icon="package" :title="$filter === 'all' ? 'Belum ada pesanan.' : 'Belum ada pesanan dengan status ini.'">
                                @if($filter === 'all')
                                    <a href="{{ url('/') }}" class="btn btn-primary">Belanja Sekarang</a>
                                @endif
                            </x-mp.empty>
                        </section>
                    @endforelse
                </div>

                {{ $orders->links('components.mp.pagination') }}
            </div>

            <aside class="stack">
                <section class="panel" aria-labelledby="h-month">
                    <div class="panel-head"><h2 id="h-month">{{ $monthLabel }}</h2></div>
                    <div class="stat-grid num">
                        <div class="stat"><b>{{ $monthCount }}</b><span>Pesanan</span></div>
                        <div class="stat"><b>{{ $monthSpent }}</b><span>Belanja (Rp)</span></div>
                        <div class="stat"><b>{{ $monthDone }}</b><span>Selesai</span></div>
                    </div>
                </section>
                @if(auth()->user()->buyerProfile?->salesUser)
                    <section class="panel" aria-labelledby="h-rep">
                        <div class="panel-head"><h2 id="h-rep">Sales Anda</h2></div>
                        <x-mp.sales-contact />
                    </section>
                @endif
            </aside>
        </div>
    </main>

</div>

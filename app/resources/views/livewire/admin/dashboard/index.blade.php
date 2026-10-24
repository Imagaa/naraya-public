<div class="adm-page" wire:poll.60s>
    {{-- phones only, until the admin app is on the home screen --}}
    <a href="{{ route('admin.install') }}" class="adm-install-hint" x-data x-show="! (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true)"><x-lucide-smartphone class="ico" /><span>Pasang aplikasi admin di HP</span><x-lucide-chevron-right class="ico" /></a>
    <x-admin.page-header :title="$greeting">
        {{ \App\Support\BusinessDay::now()->locale('id')->translatedFormat('l, j F Y') }} ·
        @if($pending > 0)
            <strong>{{ $pending }} hal perlu ditangani</strong>{{ $lateQueues > 0 ? ', '.$lateQueues.' antrean sudah lewat batas' : '' }}.
        @else
            tidak ada yang menunggu.
        @endif
    </x-admin.page-header>

    @if($pending > 0)
        <x-admin.section title="Perlu ditangani" :aside="'Diperbarui '.\App\Support\BusinessDay::now()->format('H.i')">
            <div class="adm-queue-groups">
                @foreach($groups as $group => $queues)
                    <x-admin.queue-group :title="$group">
                        @foreach($queues as $q)
                            <x-admin.queue-row :href="$q['url']" :label="$q['label']" :meta="$q['meta']" :count="$q['count']" :severity="$q['severity']" />
                        @endforeach
                    </x-admin.queue-group>
                @endforeach
            </div>
            <ul class="adm-legend">
                <li class="is-late"><span class="adm-dot"></span>Lewat batas waktu / ada sengketa</li>
                <li class="is-soon"><span class="adm-dot"></span>Lewat setengah batas</li>
                <li><span class="adm-dot"></span>Masih aman</li>
            </ul>
        </x-admin.section>
    @else
        <div class="adm-allclear">
            <span class="adm-allclear-icon"><x-lucide-check width="22" height="22" /></span>
            <div>
                <h2>Semua beres</h2>
                <p>Tidak ada order, setoran, stok, atau penukaran yang menunggu. Antrean baru akan muncul di sini.</p>
            </div>
        </div>
    @endif

    {{-- who is out in the field right now; opens the live map (Admin\Tracking\Index) --}}
    <a href="{{ route('admin.tracking.index') }}" class="adm-working">
        <span class="adm-working-icon"><x-lucide-navigation width="20" height="20" /></span>
        <span class="adm-working-text">
            <b>{{ $workingCount }} dari {{ $activeSalesCount }} sales sedang bekerja</b>
            <small>{{ $working->isEmpty() ? 'Belum ada yang absen masuk hari ini' : $working->map(fn ($w) => $w['name'].' sejak '.$w['since'])->join(' · ') }}</small>
        </span>
        <span class="adm-working-go">Lihat perjalanan →</span>
    </a>

    <x-admin.section title="Ringkasan hari ini">
        <div class="adm-kpis">
            <x-admin.kpi label="Omzet hari ini" :value="$this->rupiah($salesToday)" :note="'Kemarin '.$this->rupiah($salesYesterday).' · order selesai'" />
            <x-admin.kpi label="Order sedang jalan" :value="$ordersInFlight" note="Baru, diproses, dikirim" />
            <x-admin.kpi label="Kas di tangan sales" :value="$this->rupiah($cashHeld)" :note="$salesHoldingCash.' sales belum setor'" />
            <x-admin.kpi :label="'Omzet '.$monthLabel" :value="$this->rupiah($salesMonth)" :note="$targetTotal > 0 ? $targetPercent.'% dari target '.$this->rupiah($targetTotal) : 'Target belum diatur'" :progress="$targetPercent" />
        </div>
        <a class="adm-more" href="{{ route('admin.reports.index') }}">Grafik &amp; laporan lengkap →</a>
    </x-admin.section>
</div>

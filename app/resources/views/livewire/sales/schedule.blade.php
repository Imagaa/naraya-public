{{-- Atur jadwal: ticks live on the phone across all seven days; one Simpan. --}}
<x-sales.page title="Atur jadwal" back="1">
    <div x-data="storeSchedule(@js($days), @js($stores), {{ $today }})">
        <p class="sales-note">Pilih toko yang Anda kunjungi tiap hari. Jadwal berulang setiap minggu; toko hari ini tampil di halaman Kerja.</p>

        <div class="sched-days" role="tablist" aria-label="Hari">
            @foreach(\App\Support\Sales\StoreSchedules::SHORT as $d => $short)
                <button type="button" role="tab" class="sched-day" :class="{ 'is-on': day === {{ $d }} }" :aria-selected="(day === {{ $d }}).toString()"
                        x-on:click="day = {{ $d }}" aria-label="{{ \App\Support\Sales\StoreSchedules::DAYS[$d] }}">
                    <span>{{ $short }}</span><b class="num" x-text="picked[{{ $d }}].length"></b>@if($d === $today)<i class="sched-today" aria-hidden="true"></i>@endif
                </button>
            @endforeach
        </div>

        <div class="sched-tools">
            <label class="ssearch-field">
                <x-lucide-search class="ico" />
                <input type="search" class="input" x-model="q" autocomplete="off" placeholder="Cari toko…" aria-label="Cari toko">
            </label>
            <x-ui.checkbox label="Belum dijadwalkan" chip x-model="unscheduledOnly" />
        </div>

        <p class="sched-summary"><b x-text="dayName"></b>: <span x-text="picked[day].length"></span> toko dipilih</p>

        <ul class="sched-list">
            <template x-for="s in shown" :key="s.id">
                <li>
                    <label class="sched-store">
                        <input type="checkbox" :checked="picked[day].includes(s.id)" x-on:change="toggle(s.id)">
                        <span><b x-text="s.name"></b><small x-text="s.sub"></small><small class="sched-other" x-show="others(s.id)" x-text="others(s.id)"></small></span>
                    </label>
                </li>
            </template>
        </ul>
        <p class="sales-note" x-show="!shown.length">Tidak ada toko yang cocok.</p>

        <div class="sched-save">
            <button type="button" class="btn btn-primary btn-block" :disabled="!dirty || saving" x-on:click="save()" x-text="saving ? 'Menyimpan…' : 'Simpan jadwal'">Simpan jadwal</button>
        </div>
        <x-ui.field-error name="jadwal" />
    </div>
</x-sales.page>

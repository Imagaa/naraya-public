{{-- Live camera + geofence chip for a check-in or check-out (resources/js/attendance.js, cfg from App\Support\AbsenCamera). --}}
@props(['cfg'])

@if($cfg['locations']->isEmpty())
    <p class="sales-note">Belum ada lokasi absen untuk Anda, hubungi admin.</p>
@else
    <div class="sales-form" wire:ignore x-data="absenCamera(@js($cfg))">
        <p class="absen-chip" :class="'is-' + chip.tone" role="status" x-text="chip.text">Mencari GPS…</p>

        <div class="absen-viewfinder">
            <video x-ref="video" x-show="!photoUrl" autoplay playsinline muted></video>
            <img x-show="photoUrl" :src="photoUrl" alt="Pratinjau foto absen">
            <div class="absen-stamp" aria-hidden="true">
                <span x-text="stamp[0]"></span><span x-text="stamp[1]"></span><span x-text="stamp[2]"></span>
            </div>
        </div>
        <p class="sales-note" x-show="cameraError" x-text="cameraError"></p>

        <div class="field">
            <label for="attendance-note">Catatan (opsional)</label>
            <input type="text" id="attendance-note" class="input" x-ref="note" maxlength="500">
        </div>

        <template x-if="!photoUrl">
            {{-- the photo waits for the location: the watermark and the geofence both need it --}}
            <x-ui.button variant="primary" x-on:click="capture()" x-bind:disabled="!stream || !located"><span x-text="located ? 'Ambil foto' : 'Mencari lokasi…'">Mencari lokasi…</span></x-ui.button>
        </template>
        <template x-if="photoUrl">
            <div class="absen-actions">
                <x-ui.button variant="secondary" x-on:click="retake()">Ulangi</x-ui.button>
                <x-ui.button variant="primary" x-on:click="submit()" x-bind:disabled="sending || chip.tone !== 'ok'">Kirim Absen {{ $cfg['tipeLabel'] }}</x-ui.button>
            </div>
        </template>
        <p class="sales-note" role="status" x-text="status"></p>
    </div>
@endif

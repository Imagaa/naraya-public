{{-- Store rows on Kerja. `actions`: today's list, with navigate and Mulai/Kunjungan; otherwise ("Semua toko") the schedule days. --}}
<ol class="work-stores">
    @foreach($rows as $s)
        <li @class(['work-store', 'is-'.$s['status']]) wire:key="{{ $actions ? 's' : 'a' }}-{{ $s['id'] }}">
            <button type="button" class="work-store-main" wire:click="open('toko', {{ $s['id'] }})" data-offline="toko:{{ $s['id'] }}">
                <b>{{ $s['name'] }}</b><small>{{ $s['sub'] }}</small>@if($s['incomplete'])<small class="text-danger">Data toko belum lengkap</small>@endif
            </button>
            @if(! $actions)
                <span @class(['work-store-days', 'is-none' => ! $s['days']])>{{ $s['days'] ?? 'Belum dijadwalkan' }}</span>
            @elseif($s['status'] === 'pending')
                @if($s['lat'] !== null)<a class="btn btn-secondary btn-icon" href="{{ \App\Support\Sales\DeliveryRoutes::mapsUrl([$s]) }}" target="_blank" rel="noopener" aria-label="Navigasi ke {{ $s['name'] }}"><x-lucide-navigation class="ico" /></a>@endif
                @if($s['opened'])
                    <x-ui.button variant="primary" wire:click="open('titip', {{ $s['id'] }})" data-offline="titip:{{ $s['id'] }}">Kunjungan</x-ui.button>
                @else
                    <x-ui.button variant="primary" wire:click="open('stok-awal', {{ $s['id'] }})" data-offline="awal:{{ $s['id'] }}">Mulai</x-ui.button>
                @endif
            @endif
        </li>
    @endforeach
</ol>

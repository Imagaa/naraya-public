<x-sales.page title="Target Bulanan" :back="route('sales.more')">
    <x-sales.hero :realized="$realized" :target="$target" />

    <x-sales.section title="3 Bulan Terakhir">
        <div class="sales-list">
            @foreach($history as $item)
                <x-sales.row>
                    <span>{{ $item['label'] }}</span>
                    <x-slot:trail><b>@rupiah($item['realized'])</b></x-slot:trail>
                </x-sales.row>
            @endforeach
        </div>
    </x-sales.section>
</x-sales.page>

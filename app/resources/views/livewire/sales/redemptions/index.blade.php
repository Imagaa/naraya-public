<x-sales.page title="Hadiah untuk Diserahkan" :back="route('sales.more')">
    <div class="sales-list">
        @forelse($redemptions as $redemption)
            @php($profile = $redemption->user->buyerProfile)
            <x-ui.card wire:key="redemption-{{ $redemption->id }}">
                <div>
                    <b>{{ $redemption->reward->name }}</b>
                    <p class="sales-note">untuk {{ $redemption->user->name }}@if($profile?->address) · {{ $profile->address }}@endif</p>
                </div>
                <x-sales.contact :phone="$profile?->phone" :lat="$profile?->latitude" :lng="$profile?->longitude" :address="$profile?->address" />
                <form wire:submit="markDelivered({{ $redemption->id }})" class="sales-form">
                    <x-ui.camera-photo :wire-model="'proofPhotos.'.$redemption->id" label="Foto bukti serah terima" />
                    <x-ui.field-error :name="'proofPhotos.'.$redemption->id" />
                    <x-ui.button type="submit" variant="primary">Tandai Diserahkan</x-ui.button>
                </form>
            </x-ui.card>
        @empty
            <x-ui.empty-state message="Tidak ada hadiah yang perlu diserahkan saat ini." />
        @endforelse
    </div>
</x-sales.page>

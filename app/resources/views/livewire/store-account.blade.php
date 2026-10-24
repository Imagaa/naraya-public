{{-- "Akun toko" on the sales and admin store pages: no login yet → make one (email or phone); otherwise reissue. --}}
<div>
    @if($issued)
        <x-ui.issued-password :name="$store->name" :login="$store->loginLabel()" :password="$issued" :pin="$store->usesPin()" :phone="$store->buyerProfile?->phone" />
    @else
        <x-ui.card title="Akun toko">
            <div class="store-account">
                @if(! $store->hasAccount())
                    <p class="store-account-state">Belum punya akun — toko ini belum bisa masuk ke Naraya.</p>
                    @if($creating)
                        <form wire:submit="create" class="store-account-form">
                            <div class="line-switch" role="group" aria-label="Cara masuk">
                                <button type="button" wire:click="$set('method', 'phone')" aria-pressed="{{ $method === 'phone' ? 'true' : 'false' }}">Nomor HP + PIN</button>
                                <button type="button" wire:click="$set('method', 'email')" aria-pressed="{{ $method === 'email' ? 'true' : 'false' }}">Email + kata sandi</button>
                            </div>
                            @if($method === 'phone')
                                <x-ui.input label="Nomor HP untuk masuk" wire:model="phone" inputmode="tel" autocomplete="off" />
                                <x-ui.field-error name="phone" />
                                <p class="store-account-hint">PIN sementara 6 angka dibuat otomatis dan ditampilkan sekali. Pemilik wajib membuat PIN sendiri saat pertama kali masuk.</p>
                            @else
                                <x-ui.input label="Email untuk masuk" type="email" wire:model="email" autocomplete="off" />
                                <x-ui.field-error name="email" />
                                <p class="store-account-hint">Kata sandi sementara dibuat otomatis dan ditampilkan sekali. Pemilik wajib menggantinya saat pertama kali masuk.</p>
                            @endif
                            <div class="store-account-actions">
                                <x-ui.button type="submit" variant="primary" wire:loading.attr="disabled">Buat akun</x-ui.button>
                                <x-ui.button type="button" variant="secondary" wire:click="$set('creating', false)">Batal</x-ui.button>
                            </div>
                        </form>
                    @else
                        <x-ui.button type="button" variant="primary" wire:click="$set('creating', true)">Buatkan akun</x-ui.button>
                    @endif
                @else
                    <p class="store-account-state">
                        Masuk dengan {{ $store->usesPin() ? 'nomor HP' : 'email' }} <b>{{ $store->loginLabel() }}</b>
                        @if($store->buyerProfile?->status === \App\Enums\BuyerStatus::Pending) · <x-ui.tag variant="accent">Menunggu persetujuan admin</x-ui.tag>@endif
                    </p>
                    @if(auth()->user()->isAdmin())
                    <x-ui.button type="button" variant="secondary" wire:click="reissue" wire:confirm="Buat {{ $store->usesPin() ? 'PIN' : 'kata sandi' }} sementara baru untuk {{ $store->name }}? Pemilik wajib menggantinya saat masuk.">Reset {{ $store->usesPin() ? 'PIN' : 'kata sandi' }}</x-ui.button>
                    @else
                        <p class="store-account-hint">Lupa {{ $store->usesPin() ? 'PIN' : 'kata sandi' }}? Minta admin membuatkan yang sementara.</p>
                    @endif
                @endif
            </div>
        </x-ui.card>
    @endif
</div>

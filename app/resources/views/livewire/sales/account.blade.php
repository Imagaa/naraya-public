@php($profile = $user->salesProfile)
<x-sales.page title="Akun" :back="route('sales.more')">
    @if($statusMessage)
        <p class="sales-note" role="status">{{ $statusMessage }}</p>
    @endif

    <x-sales.section title="Foto profil">
        <div class="sales-account-photo">
            {{-- Only images can be previewed; anything else is rejected by savePhoto() with a message. --}}
            @if($photo && $photo->isPreviewable())
                <img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau foto baru" class="sales-account-avatar">
            @else
                <x-ui.person-avatar :user="$user" :size="88" />
            @endif
            <div>
                <b>{{ $user->name }}</b>
                <p class="sales-note">Foto ini dilihat pembeli dan tim Naraya.</p>
            </div>
        </div>
        <form wire:submit="savePhoto" class="sales-form">
            <x-ui.file-picker id="account-photo" wire:model="photo" :file="$photo" hint="Dari kamera atau galeri · maks. 5 MB" />
            <x-ui.field-error name="photo" />
            @if($photo)
                <x-ui.save-button target="photo" :changed="$photo !== null">Simpan foto</x-ui.save-button>
            @endif
        </form>
    </x-sales.section>

    <x-sales.section title="Nomor WhatsApp">
        <form wire:submit="requestPhoneChange" class="sales-form">
            <p class="sales-note">Nomor aktif: <b>{{ $profile?->phone ?: '—' }}</b></p>
            @if($profile?->pending_phone)
                <p class="sales-note">Menunggu persetujuan admin: <b>{{ $profile->pending_phone }}</b>. Sampai disetujui, pembeli tetap melihat nomor lama.</p>
            @endif
            <x-ui.input label="Nomor baru" type="tel" inputmode="tel" wire:model="phone" autocomplete="tel" />
            <x-ui.field-error name="phone" />
            <x-ui.save-button target="phone" variant="secondary">Ajukan ganti nomor</x-ui.save-button>
        </form>
    </x-sales.section>

    <x-sales.section title="Ganti kata sandi">
        <form wire:submit="updatePassword" class="sales-form">
            <x-ui.input label="Kata sandi saat ini" type="password" wire:model="currentPassword" autocomplete="current-password" />
            <x-ui.field-error name="currentPassword" />
            <x-ui.input label="Kata sandi baru" type="password" wire:model="newPassword" autocomplete="new-password" />
            <x-ui.field-error name="newPassword" />
            <x-ui.input label="Ulangi kata sandi baru" type="password" wire:model="newPassword_confirmation" autocomplete="new-password" />
            <x-ui.save-button target="currentPassword,newPassword,newPassword_confirmation" variant="secondary">Simpan kata sandi</x-ui.save-button>
        </form>
    </x-sales.section>

    <x-sales.section title="Data akun">
        <div class="sales-list">
            <x-sales.row><span>Nama</span><x-slot:trail><b>{{ $user->name }}</b></x-slot:trail></x-sales.row>
            <x-sales.row><span>Email</span><x-slot:trail><b>{{ $user->email }}</b></x-slot:trail></x-sales.row>
            <x-sales.row><span>Area</span><x-slot:trail><b>{{ $profile?->area?->name ?? '—' }}</b></x-slot:trail></x-sales.row>
        </div>
        <p class="sales-note">Nama, email, dan area diatur oleh Admin.</p>
    </x-sales.section>
</x-sales.page>

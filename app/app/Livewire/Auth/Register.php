<?php

namespace App\Livewire\Auth;

use App\Notifications\AdminNotice;
use App\Enums\BuyerStatus;
use App\Enums\Role;
use App\Livewire\Concerns\CapturesStorefront;
use App\Livewire\Marketplace\Concerns\ManagesAddressForm;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\User;
use App\Support\LoginSecret;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Buyer self-registration, logging in by phone + PIN or email + password
 *. Every field is required, the
 * account starts as pending until an Admin approves it, and the response never
 * reveals whether an email or phone is already registered: a taken login gets
 * the same confirmation and nothing is created or stored. When the store phone
 * matches exactly one store a sales registered without a login, the login goes
 * onto that store instead of a new one (the owner claiming it; admin approves).
 */
class Register extends Component
{
    use CapturesStorefront, ManagesAddressForm;

    public string $storeName = '';
    public string $ownerName = '';
    public string $phone = '';
    /** phone (+ PIN) | email (+ password) */
    public string $method = 'phone';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $pin = '';
    public string $pin_confirmation = '';

    public bool $submitted = false;

    public function register(): void
    {
        $key = 'register|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError($this->method === 'email' ? 'email' : 'phone', 'Terlalu banyak pendaftaran dari jaringan ini. Coba lagi nanti.');

            return;
        }

        $byPin = $this->method !== 'email';
        $this->validate([
            'storeName' => 'required|string|max:255',
            'ownerName' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^(\+?62|0)8[0-9\- ]{7,14}$/'],
            ...($byPin
                ? ['pin' => LoginSecret::rules(true)]
                : ['email' => 'required|email|max:255', 'password' => LoginSecret::rules(false)]), // no unique rule: that would reveal registered logins
            'storePhoto' => ['required', 'image', 'max:5120'],
        ], [
            'storeName.required' => 'Nama toko wajib diisi.',
            'ownerName.required' => 'Nama pemilik wajib diisi.',
            'phone.required' => 'Nomor HP/WhatsApp wajib diisi.',
            'phone.regex' => 'Masukkan nomor HP Indonesia, mis. 0812xxxxxxxx.',
            'email.required' => 'Email wajib diisi.',
            ...LoginSecret::messages($byPin, $byPin ? 'pin' : 'password'),
            ...$this->storefrontMessages(),
        ]);
        $this->validateAddressForm();

        RateLimiter::hit($key, 3600);

        $phone = Phone::normalize($this->phone);
        $login = $byPin ? ['email' => null, 'phone' => $phone] : ['email' => $this->email, 'phone' => null];
        $secret = Hash::make($byPin ? $this->pin : $this->password);
        $taken = $byPin ? User::where('phone', $phone)->exists() : User::where('email', $this->email)->exists();

        if (! $taken) {
            $claimable = BuyerProfile::where('phone', $phone)->whereHas('user', fn ($q) => $q->whereNull('password'))->with('user', 'salesUser')->get();

            $claimable->count() === 1 ? $this->claim($claimable->first(), $login, $secret) : $this->createStore($login, $secret, $phone);
        }

        $this->reset('password', 'password_confirmation', 'pin', 'pin_confirmation', 'storePhoto', 'email');
        $this->submitted = true;
    }

    /** The owner of a store a sales already registered asks for its login: the sales-captured store data stands. */
    private function claim(BuyerProfile $profile, array $login, string $secret): void
    {
        DB::transaction(function () use ($profile, $login, $secret) {
            $profile->user->forceFill($login + ['password' => $secret, 'must_change_password' => false, 'password_set_by' => null])->save();
            $profile->update(['status' => BuyerStatus::Pending, 'rejection_reason' => null]);
        });

        AdminNotice::toAdmins('Pemilik minta akun toko', "{$profile->user->name} (didaftarkan sales {$profile->salesUser?->name}) minta akun untuk masuk", route('admin.buyers.edit', $profile->user, absolute: false), 'store');
    }

    private function createStore(array $login, string $secret, ?string $phone): void
    {
        DB::transaction(function () use ($login, $secret, $phone) {
            $user = User::create(['name' => $this->storeName, 'role' => Role::User]);
            $user->forceFill($login + ['password' => $secret])->save();

            $address = $user->addresses()->create($this->addressFormData() + ['label' => 'Toko', 'is_default' => true]);

            $user->buyerProfile()->create([
                'owner_name' => $this->ownerName,
                'phone' => $phone,
                'address' => $address->toFullAddress(),
                'latitude' => $this->newLat,
                'longitude' => $this->newLng,
                'store_photo_path' => $this->storeStorefrontPhoto(),
                'area_id' => Area::nearestTo($this->newLat, $this->newLng)?->id,
                'status' => BuyerStatus::Pending,
                'is_active' => true,
            ]);

            AdminNotice::toAdmins('Toko baru mendaftar', "{$user->name} menunggu persetujuan", route('admin.buyers.index', ['antrean' => 'registrations'], absolute: false), 'store');
        });
    }

    public function render()
    {
        return view('livewire.auth.register')->layout('layouts.marketplace', ['maps' => true])->title('Daftar — Naraya');
    }
}

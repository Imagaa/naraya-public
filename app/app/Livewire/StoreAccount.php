<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\Phone;
use App\Support\TemporaryPassword;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Akun toko" on the sales and admin store pages:
 * a store can exist without a login; here sales (own stores) or admin give it one — email +
 * password or phone + PIN — and reissue a temporary one when the owner forgets it.
 */
class StoreAccount extends Component
{
    #[Locked]
    public int $storeId;

    public bool $creating = false;

    public string $method = 'phone';

    public string $email = '';

    public string $phone = '';

    /** The temporary password/PIN, shown once. */
    public ?string $issued = null;

    public function mount(int $storeId, bool $open = false): void
    {
        $this->storeId = $storeId;
        $store = $this->store();
        $this->phone = $store->buyerProfile?->phone ? '0'.substr($store->buyerProfile->phone, 2) : '';
        $this->creating = $open && ! $store->hasAccount();
    }

    public function create(): void
    {
        $store = $this->store();
        if ($store->hasAccount()) {
            $this->creating = false;

            return;
        }

        if ($this->method === 'email') {
            $this->validate(['email' => 'required|email|max:255|unique:users,email'], [
                'email.required' => 'Isi email pemilik toko.', 'email.email' => 'Format email tidak valid.', 'email.unique' => 'Email ini sudah dipakai akun lain.',
            ]);
            $login = ['email' => $this->email, 'phone' => null];
        } else {
            $this->validate(['phone' => ['required', 'regex:/^(\+?62|0)8[0-9\- ]{7,14}$/']], [
                'phone.required' => 'Isi nomor HP pemilik toko.', 'phone.regex' => 'Masukkan nomor HP Indonesia, mis. 0812xxxxxxxx.',
            ]);
            $phone = Phone::normalize($this->phone);
            if (User::where('phone', $phone)->exists()) {
                $this->addError('phone', 'Nomor ini sudah dipakai untuk masuk oleh akun lain.');

                return;
            }
            $login = ['email' => null, 'phone' => $phone];
        }

        $store->forceFill($login)->save();
        $this->issued = TemporaryPassword::issueFor($store, auth()->user());
        $this->creating = false;
    }

    /** A fresh temporary password/PIN; the owner must replace it on the next login. Admin only (2026-10-12). */
    public function reissue(): void
    {
        $store = $this->store();
        abort_unless($store->hasAccount() && auth()->user()->isAdmin(), 403);

        $this->issued = TemporaryPassword::issueFor($store, auth()->user());
    }

    /** Admin sees every store; sales only the stores assigned to them. */
    private function store(): User
    {
        $store = User::with('buyerProfile')->findOrFail($this->storeId);
        $me = auth()->user();
        abort_unless($store->isUser() && ($me->isAdmin() || ($me->isSales() && (int) $store->buyerProfile?->sales_user_id === $me->id)), 404);

        return $store;
    }

    public function render()
    {
        return view('livewire.store-account', ['store' => $this->store()]);
    }
}

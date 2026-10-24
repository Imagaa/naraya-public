<?php

namespace App\Livewire\Marketplace;

use App\Livewire\Marketplace\Concerns\HasMarketplaceShell;
use App\Livewire\Marketplace\Concerns\ManagesAddressForm;
use App\Models\Order;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Account extends Component
{
    use HasMarketplaceShell, ManagesAddressForm;

    /** The section from the URL (profil | alamat | keamanan), or null on the /akun overview. */
    public ?string $section = null;

    /** The panel shown: the URL's section, or Profil on the desktop overview. */
    public string $tab = 'profil';

    public bool $editingProfile = false;
    public string $name = '';
    public string $phone = '';

    public bool $addingAddress = false;
    public ?int $editingAddressId = null;

    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPassword_confirmation = '';

    public ?string $statusMessage = null;

    public function mount(?string $section = null): void
    {
        $this->section = $section;
        $this->tab = $section ?? 'profil';
        $this->name = auth()->user()->name;
        $this->phone = auth()->user()->buyerProfile?->phone ?? '';
    }

    public function showEditProfile(): void
    {
        $this->editingProfile = true;
        $this->statusMessage = null;
    }

    public function cancelEditProfile(): void
    {
        $this->editingProfile = false;
        $this->resetErrorBag();
        $this->name = auth()->user()->name;
        $this->phone = auth()->user()->buyerProfile?->phone ?? '';
    }

    public function saveProfile(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
        ], [
            'name.required' => 'Nama wajib diisi.',
        ]);

        auth()->user()->update(['name' => $this->name]);
        auth()->user()->buyerProfile()->updateOrCreate([], ['phone' => $this->phone ?: null]);

        $this->editingProfile = false;
        $this->statusMessage = 'Profil berhasil disimpan.';
    }

    public function showAddAddressForm(): void
    {
        $this->resetAddressForm();
        $this->editingAddressId = null;
        $this->addingAddress = true;
        $this->statusMessage = null;
    }

    public function editAddress(int $addressId): void
    {
        $address = auth()->user()->addresses()->findOrFail($addressId);

        $this->fillAddressForm($address);
        $this->editingAddressId = $address->id;
        $this->addingAddress = true;
        $this->statusMessage = null;
    }

    public function cancelAddressForm(): void
    {
        $this->addingAddress = false;
        $this->editingAddressId = null;
        $this->resetAddressForm();
        $this->resetErrorBag();
    }

    public function saveAddress(): void
    {
        $this->validateAddressForm();

        if ($this->editingAddressId) {
            auth()->user()->addresses()->findOrFail($this->editingAddressId)->update($this->addressFormData());
            $this->statusMessage = 'Alamat berhasil diperbarui.';
        } else {
            auth()->user()->addresses()->create(
                $this->addressFormData() + ['is_default' => auth()->user()->addresses()->count() === 0]
            );
            $this->statusMessage = 'Alamat baru berhasil ditambahkan.';
        }

        $this->cancelAddressForm();
    }

    public function deleteAddress(int $addressId): void
    {
        $address = auth()->user()->addresses()->findOrFail($addressId);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            auth()->user()->addresses()->first()?->update(['is_default' => true]);
        }

        $this->statusMessage = 'Alamat berhasil dihapus.';
    }

    public function setDefaultAddress(int $addressId): void
    {
        auth()->user()->addresses()->update(['is_default' => false]);
        auth()->user()->addresses()->findOrFail($addressId)->update(['is_default' => true]);

        $this->statusMessage = 'Alamat utama berhasil diperbarui.';
    }

    public function updatePassword(): void
    {
        $user = auth()->user();
        $noun = \App\Support\LoginSecret::noun($user);
        $this->validate([
            'currentPassword' => 'required|string',
            'newPassword' => \App\Support\LoginSecret::rules($user),
        ], ['currentPassword.required' => ucfirst($noun).' saat ini wajib diisi.'] + \App\Support\LoginSecret::messages($user, 'newPassword'));

        if (! Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', ucfirst($noun).' saat ini salah.');

            return;
        }

        $user->update(['password' => Hash::make($this->newPassword)]);

        $this->reset(['currentPassword', 'newPassword', 'newPassword_confirmation']);
        $this->statusMessage = ucfirst($noun).' berhasil diubah.';
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.marketplace.account', [
            'addresses' => $user->addresses()->orderByDesc('is_default')->latest()->get(),
            'cartCount' => $this->cartCount(),
            'profile' => $user->buyerProfile,
            'activeOrders' => Order::where('buyer_id', $user->id)->whereIn('status', ['new', 'processing', 'shipped'])->count(),
            'orderCount' => Order::where('buyer_id', $user->id)->count(),
            'memberSince' => $user->created_at->timezone(config('app.business_timezone'))->locale('id')->translatedFormat('M Y'),
        ])->layout('layouts.marketplace', ['maps' => true])->title('Akun — Naraya');
    }
}

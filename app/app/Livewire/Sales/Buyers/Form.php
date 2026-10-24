<?php

namespace App\Livewire\Sales\Buyers;

use App\Livewire\Concerns\CapturesStorefront;
use App\Support\Sales\StoreRegistration;
use Livewire\Component;

/**
 * Sales registers a store on the spot: name, owner, phone, pin, address (filled from the pin),
 * shop-front photo. No account yet — the login is made from the store page, now or later
 *.
 */
class Form extends Component
{
    use CapturesStorefront;

    public string $name = '';
    public string $ownerName = '';
    public string $phone = '';
    public string $address = '';

    /** The store just saved; the view then offers to make its account. */
    public ?int $savedId = null;

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'ownerName' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^(\+?62|0)8[0-9\- ]{7,14}$/'],
            'address' => 'required|string|max:500',
            ...$this->storefrontRules(photoRequired: true),
        ], [
            'phone.regex' => 'Masukkan nomor HP Indonesia, mis. 0812xxxxxxxx.',
            'address.required' => 'Isi alamat toko (terisi otomatis dari titik lokasi).',
            ...$this->storefrontMessages(),
        ]);

        $this->savedId = StoreRegistration::create(auth()->user(), $this->name, $this->ownerName, $this->phone, $this->address,
            $this->lat, $this->lng, $this->storeStorefrontPhoto())->id;
    }

    public function render()
    {
        return view('livewire.sales.buyers.form');
    }
}

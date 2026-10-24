<?php

namespace App\Livewire\Marketplace\Concerns;

use App\Models\Address;

/**
 * Shared by Checkout (add-address-during-checkout) and the account page's
 * address book (add/edit) — same fields, same validation, same region
 * cascade, just different call sites for what happens after a valid save.
 */
trait ManagesAddressForm
{
    public ?string $newLabel = null;
    public ?string $newProvinceId = null;
    public ?string $newProvinceName = null;
    public ?string $newCityId = null;
    public ?string $newCityName = null;
    public ?string $newDistrictId = null;
    public ?string $newDistrictName = null;
    public ?string $newVillageId = null;
    public ?string $newVillageName = null;
    public string $newDetail = '';
    public ?string $newPostalCode = null;
    public ?float $newLat = null;
    public ?float $newLng = null;

    protected function validateAddressForm(): void
    {
        $this->validate(
            [
                'newProvinceId' => 'required|string',
                'newProvinceName' => 'required|string',
                'newCityId' => 'required|string',
                'newCityName' => 'required|string',
                'newDistrictId' => 'required|string',
                'newDistrictName' => 'required|string',
                'newVillageId' => 'required|string',
                'newVillageName' => 'required|string',
                'newDetail' => 'required|string|max:500',
                'newLat' => 'required|numeric|between:-90,90',
                'newLng' => 'required|numeric|between:-180,180',
            ],
            [
                'newProvinceId.required' => 'Pilih provinsi.',
                'newCityId.required' => 'Pilih kota/kabupaten.',
                'newDistrictId.required' => 'Pilih kecamatan.',
                'newVillageId.required' => 'Pilih kelurahan/desa.',
                'newDetail.required' => 'Detail alamat (jalan, no. rumah, RT/RW) wajib diisi.',
                'newLat.required' => 'Tandai titik lokasi di peta.',
            ]
        );
    }

    protected function addressFormData(): array
    {
        return [
            'label' => $this->newLabel ?: null,
            'province_id' => $this->newProvinceId,
            'province_name' => $this->newProvinceName,
            'city_id' => $this->newCityId,
            'city_name' => $this->newCityName,
            'district_id' => $this->newDistrictId,
            'district_name' => $this->newDistrictName,
            'village_id' => $this->newVillageId,
            'village_name' => $this->newVillageName,
            'detail' => $this->newDetail,
            'postal_code' => $this->newPostalCode ?: null,
            'lat' => $this->newLat,
            'lng' => $this->newLng,
        ];
    }

    protected function resetAddressForm(): void
    {
        $this->newLabel = null;
        $this->newProvinceId = null;
        $this->newProvinceName = null;
        $this->newCityId = null;
        $this->newCityName = null;
        $this->newDistrictId = null;
        $this->newDistrictName = null;
        $this->newVillageId = null;
        $this->newVillageName = null;
        $this->newDetail = '';
        $this->newPostalCode = null;
        $this->newLat = null;
        $this->newLng = null;
    }

    protected function fillAddressForm(Address $address): void
    {
        $this->newLabel = $address->label;
        $this->newProvinceId = $address->province_id;
        $this->newProvinceName = $address->province_name;
        $this->newCityId = $address->city_id;
        $this->newCityName = $address->city_name;
        $this->newDistrictId = $address->district_id;
        $this->newDistrictName = $address->district_name;
        $this->newVillageId = $address->village_id;
        $this->newVillageName = $address->village_name;
        $this->newDetail = $address->detail;
        $this->newPostalCode = $address->postal_code;
        $this->newLat = $address->lat;
        $this->newLng = $address->lng;
    }
}

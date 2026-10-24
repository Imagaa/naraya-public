<?php

namespace App\Livewire\Admin\WarehouseSettings;

use App\Models\WarehouseSetting;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Edit extends Component
{
    use HasAdminShell;

    public string $address = '';
    public ?float $lat = null;
    public ?float $lng = null;

    public bool $saved = false;

    public function mount(): void
    {
        $warehouse = WarehouseSetting::first();

        $this->address = (string) $warehouse->address;
        $this->lat = $warehouse->lat;
        $this->lng = $warehouse->lng;
    }

    public function save(): void
    {
        $this->validate([
            'address' => 'nullable|string|max:255',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ], [
            'lat.required' => 'Tandai titik lokasi gudang di peta.',
        ]);

        WarehouseSetting::first()->update([
            'address' => $this->address ?: null,
            'lat' => $this->lat,
            'lng' => $this->lng,
        ]);

        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.admin.warehouse-settings.edit')->layout('layouts.admin');
    }
}

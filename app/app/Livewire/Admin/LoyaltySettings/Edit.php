<?php

namespace App\Livewire\Admin\LoyaltySettings;

use App\Models\LoyaltySetting;
use App\Support\Loyalty\StorePoints;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Edit extends Component
{
    use HasAdminShell;
    public int $pcsPerPoint = 0;

    public bool $saved = false;

    public function mount(): void
    {
        $this->pcsPerPoint = app(StorePoints::class)->perPoint();
    }

    public function save()
    {
        $this->validate(['pcsPerPoint' => 'required|integer|min:1']);

        LoyaltySetting::first()->update(['pcs_per_point' => $this->pcsPerPoint]);
        app(StorePoints::class)->syncAll(); // the new rate applies to every store's whole history

        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.admin.loyalty-settings.edit')->layout('layouts.admin');
    }
}

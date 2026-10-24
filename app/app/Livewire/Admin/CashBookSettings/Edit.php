<?php

namespace App\Livewire\Admin\CashBookSettings;

use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\CashBookSetting;
use Livewire\Component;

class Edit extends Component
{
    use HasAdminShell;

    public int $closingDay = 5;

    public bool $saved = false;

    public function mount(): void
    {
        $this->closingDay = CashBookSetting::current()->closing_day;
    }

    public function save(): void
    {
        $this->validate(['closingDay' => 'required|integer|between:1,28'], ['closingDay.between' => 'Pilih tanggal 1–28 (ada di setiap bulan).']);
        CashBookSetting::current()->update(['closing_day' => $this->closingDay]);
        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.admin.cash-book-settings.edit')->layout('layouts.admin');
    }
}

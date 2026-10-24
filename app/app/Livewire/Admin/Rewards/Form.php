<?php

namespace App\Livewire\Admin\Rewards;

use App\Models\Reward;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Form extends Component
{
    use WithFileUploads, HasAdminShell;

    public ?Reward $reward = null;

    public string $name = '';
    public int $pointCost = 0;
    public int $stock = 0;
    public bool $isActive = true;

    public $foto;

    public function mount(?Reward $reward = null): void
    {
        $this->reward = $reward;

        if ($reward?->exists) {
            $this->name = $reward->name;
            $this->pointCost = $reward->point_cost;
            $this->stock = $reward->stock;
            $this->isActive = $reward->is_active;
        }
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'pointCost' => 'required|integer|min:1',
            'stock' => 'required|integer|min:0',
            'foto' => 'nullable|image|max:5120',
        ]);

        $data = [
            'name' => $this->name,
            'point_cost' => $this->pointCost,
            'stock' => $this->stock,
            'is_active' => $this->isActive,
        ];

        if ($this->foto) {
            $data['photo_path'] = $this->foto->store('rewards', 's3');
        }

        if ($this->reward?->exists) {
            $this->reward->update($data);
        } else {
            Reward::create($data);
        }

        return redirect()->route('admin.rewards.index');
    }

    public function render()
    {
        return view('livewire.admin.rewards.form')->layout('layouts.admin');
    }
}

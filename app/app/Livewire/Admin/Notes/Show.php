<?php

namespace App\Livewire\Admin\Notes;

use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\SalesNote;
use App\Support\Sales\SalesNotes;
use Livewire\Component;

/** One sales note: items, proof photo, and admin's verification. */
class Show extends Component
{
    use HasAdminShell;

    public SalesNote $note;

    public string $reason = '';

    public function mount(SalesNote $note): void
    {
        $this->note = $note;
    }

    public function verify(SalesNotes $notes): void
    {
        $this->resetErrorBag();
        $notes->verify($this->note, auth()->user());
    }

    public function reject(SalesNotes $notes): void
    {
        $this->resetErrorBag();
        $notes->reject($this->note, auth()->user(), $this->reason);
        $this->reset('reason');
    }

    public function render()
    {
        $this->note->load(['items.product', 'buyer.buyerProfile', 'salesUser', 'reviewer', 'deposit']);

        return view('livewire.admin.notes.show')->layout('layouts.admin');
    }
}

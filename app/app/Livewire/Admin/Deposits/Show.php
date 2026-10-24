<?php

namespace App\Livewire\Admin\Deposits;

use App\Enums\CashDepositStatus;
use App\Models\CashDeposit;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Show extends Component
{
    use HasAdminShell;
    public CashDeposit $deposit;

    public string $resolutionNote = '';

    public function mount(CashDeposit $deposit): void
    {
        $this->deposit = $deposit->load('salesUser.salesProfile', 'collections.order', 'notes.buyer', 'investments.buyer', 'resolvedBy');
    }

    public function verify()
    {
        abort_unless($this->deposit->status === CashDepositStatus::Submitted, 403);

        $matches = $this->deposit->claimed_amount === $this->deposit->expected_amount;

        $this->deposit->update([
            'status' => $matches ? CashDepositStatus::Verified : CashDepositStatus::Disputed,
            'verified_at' => $matches ? now() : null,
        ]);

        $this->deposit->refresh();
    }

    public function resolveDispute()
    {
        abort_unless($this->deposit->status === CashDepositStatus::Disputed, 403);

        $this->validate(['resolutionNote' => 'required|string|max:500']);

        $this->deposit->update([
            'status' => CashDepositStatus::Resolved,
            'resolution_note' => $this->resolutionNote,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        $this->deposit->refresh();
    }

    public function render()
    {
        return view('livewire.admin.deposits.show')->layout('layouts.admin');
    }
}

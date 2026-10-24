<?php

namespace App\Livewire\Marketplace\Onboarding;

use App\Enums\BuyerStatus;
use Livewire\Component;

/** Gate 3: a self-registered buyer waits here until an Admin approves, or sees why they were rejected. */
class AccountStatus extends Component
{
    public function mount()
    {
        if (auth()->user()->buyerProfile?->status === BuyerStatus::Approved) {
            return redirect('/');
        }
    }

    public function render()
    {
        $profile = auth()->user()->buyerProfile;

        return view('livewire.marketplace.onboarding.account-status', [
            'rejected' => $profile?->status === BuyerStatus::Rejected,
            'reason' => $profile?->rejection_reason,
            'whatsapp' => config('app.support_whatsapp'),
        ])->layout('layouts.marketplace')->title('Status akun — Naraya');
    }
}

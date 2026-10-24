<?php

namespace App\Observers;

use App\Models\Reward;
use App\Notifications\AdminNotice;

class RewardObserver
{
    /** The catalogue just ran out of this reward. */
    public function updated(Reward $reward): void
    {
        if ($reward->wasChanged('stock') && (int) $reward->stock === 0 && (int) $reward->getOriginal('stock') > 0) {
            AdminNotice::toAdmins('Stok hadiah habis', $reward->name, route('admin.rewards.edit', $reward, absolute: false), 'gift', 'warning');
        }
    }
}

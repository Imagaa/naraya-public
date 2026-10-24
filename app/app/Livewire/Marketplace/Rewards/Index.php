<?php

namespace App\Livewire\Marketplace\Rewards;

use App\Enums\RedemptionStatus;
use App\Livewire\Marketplace\Concerns\HasMarketplaceShell;
use App\Models\Redemption;
use App\Models\Reward;
use App\Support\Loyalty\StorePoints;
use Livewire\Component;

class Index extends Component
{
    use HasMarketplaceShell;

    public function redeem(Reward $reward)
    {
        $buyerProfile = auth()->user()->buyerProfile;

        if (! $buyerProfile || $buyerProfile->loyalty_points < $reward->point_cost) {
            $this->addError('redeem', 'Point Anda tidak cukup untuk menukar hadiah ini.');

            return;
        }

        if ($reward->stock < 1) {
            $this->addError('redeem', 'Stok hadiah ini sedang habis.');

            return;
        }

        Redemption::create([
            'user_id' => auth()->id(),
            'reward_id' => $reward->id,
            'point_cost' => $reward->point_cost,
            'status' => RedemptionStatus::Diajukan,
        ]);
    }

    public function render()
    {
        $balance = (int) (auth()->user()->buyerProfile?->loyalty_points ?? 0);
        $rewards = Reward::where('is_active', true)->orderBy('point_cost')->get();

        return view('livewire.marketplace.rewards.index', [
            'rewards' => $rewards,
            'pointBalance' => $balance,
            'nextReward' => $rewards->first(fn (Reward $r) => $r->point_cost > $balance && $r->stock > 0),
            'progress' => app(StorePoints::class)->progress(auth()->id()),
            'myRedemptions' => Redemption::where('user_id', auth()->id())->with('reward')->orderByDesc('id')->get(),
            'cartCount' => $this->cartCount(),
        ])->layout('layouts.marketplace')->title('Tukar Hadiah — Naraya');
    }
}

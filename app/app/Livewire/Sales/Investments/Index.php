<?php

namespace App\Livewire\Sales\Investments;

use App\Models\BuyerProfile;
use App\Models\InvestmentPurchase;
use App\Support\Investment\Investments;
use Livewire\Component;

/** Investasi Toko (sales): record packages a store bought with cash handed to the sales person. */
class Index extends Component
{
    public ?int $storeId = null;

    public int $qty = 1;

    public bool $received = false;

    public ?string $flash = null;

    public function mount(?int $storeId = null): void
    {
        $this->storeId = $storeId;
    }

    public function changeQty(int $by): void
    {
        $this->qty = max(1, min(1000, $this->qty + $by));
    }

    public function save(Investments $investments): void
    {
        $this->resetErrorBag();
        $this->validate(
            ['storeId' => 'required|integer', 'qty' => 'required|integer|min:1|max:1000', 'received' => 'accepted'],
            ['storeId.required' => 'Pilih toko.', 'received.accepted' => 'Centang bahwa uang tunainya sudah Anda terima.'],
        );

        $p = $investments->buyViaSales(auth()->user(), $this->storeId, $this->qty);
        $this->reset('storeId', 'qty', 'received');
        $this->flash = $p->number().' tercatat. Setorkan Rp'.number_format($p->amount, 0, ',', '.').' bersama setoran '.\App\Support\ActiveLine::investmentCashLine()->label().' Anda.';
    }

    public function render()
    {
        $price = config('naraya.investment.package_price');

        return view('livewire.sales.investments.index', [
            'stores' => BuyerProfile::where('sales_user_id', auth()->id())->with('user:id,name')->get()
                ->sortBy(fn ($p) => $p->user?->name)->values(),
            'amount' => $this->qty * $price,
            'monthly' => $this->qty * config('naraya.investment.monthly_return'),
            'terms' => $this->storeId ? app(Investments::class)->termsFor(\App\Models\User::find($this->storeId)) : null,
            'cashLine' => \App\Support\ActiveLine::investmentCashLine()->label(),
            'purchases' => InvestmentPurchase::where('sales_id', auth()->id())->with(['buyer:id,name', 'deposit:id,status'])->latest('id')->limit(30)->get(),
        ]);
    }
}

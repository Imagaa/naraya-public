<?php

namespace App\Livewire\Sales\Deposits;

use App\Enums\BusinessLine;
use App\Enums\CashCollectionStatus;
use App\Enums\CashDepositStatus;
use App\Enums\NotePaymentMethod;
use App\Models\CashCollection;
use App\Models\CashDeposit;
use App\Models\InvestmentPurchase;
use App\Models\SalesNote;
use App\Support\ActiveLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Setoran cash for the active line: the sales
 * person's undeposited cash notes, plus — Naraya only — the held cash of
 * legacy marketplace orders so it isn't stranded.
 */
class Index extends Component
{
    /** Shown inside the check-out sheet: no close button of its own. */
    public bool $embedded = false;

    public ?int $claimedAmount = null;
    public string $proofNote = '';

    public function mount(): void
    {
        $held = $this->total($this->pending());
        $this->claimedAmount = $held > 0 ? $held : null;
    }

    public function submitDeposit()
    {
        $this->validate(['claimedAmount' => 'required|integer|min:0']);

        $line = ActiveLine::current();
        $pending = $this->pending();

        if ($pending->every->isEmpty()) {
            $this->addError('claimedAmount', 'Tidak ada cash yang perlu disetor.');

            return;
        }

        DB::transaction(function () use ($line, $pending) {
            $deposit = CashDeposit::create([
                'line' => $line,
                'sales_id' => auth()->id(),
                'expected_amount' => $this->total($pending),
                'claimed_amount' => $this->claimedAmount,
                'status' => CashDepositStatus::Submitted,
                'proof_note' => $this->proofNote ?: null,
            ]);

            CashCollection::whereIn('id', $pending['collections']->pluck('id'))->update([
                'cash_deposit_id' => $deposit->id,
                'status' => CashCollectionStatus::Deposited,
            ]);
            SalesNote::whereIn('id', $pending['notes']->pluck('id'))->update(['cash_deposit_id' => $deposit->id]);
            InvestmentPurchase::whereIn('id', $pending['investments']->pluck('id'))->update(['cash_deposit_id' => $deposit->id]);
        });

        $this->claimedAmount = null;
        $this->proofNote = '';
        $this->dispatch('deposit-submitted'); // the check-out sheet waits for this
    }

    /** @return Collection{collections: Collection, notes: Collection} */
    private function pending(): Collection
    {
        $line = ActiveLine::current();

        return collect([
            'collections' => $line === BusinessLine::Naraya
                ? CashCollection::where('sales_id', auth()->id())->where('status', CashCollectionStatus::Held)->with('order')->get()
                : collect(),
            'notes' => SalesNote::forLine($line)->where('sales_id', auth()->id())
                ->where('payment_method', NotePaymentMethod::Cash)->whereNull('cash_deposit_id')
                ->with('buyer:id,name')->oldest('id')->get(),
            // investment cash taken from stores rides on the Naraya deposit (Toys while grosir is closed)
            'investments' => $line === ActiveLine::investmentCashLine()
                ? InvestmentPurchase::where('sales_id', auth()->id())->where('payment_method', 'cash_sales')->whereNull('cash_deposit_id')
                    ->where('status', '!=', 'ditolak')->with('buyer:id,name')->oldest('id')->get()
                : collect(),
        ]);
    }

    private function total(Collection $pending): int
    {
        return (int) ($pending['collections']->sum('amount') + $pending['notes']->sum('deposit_total') + $pending['investments']->sum('amount'));
    }

    public function render()
    {
        $pending = $this->pending();

        return view('livewire.sales.deposits.index', [
            'heldCollections' => $pending['collections'],
            'cashNotes' => $pending['notes'],
            'cashInvestments' => $pending['investments'],
            'expectedAmount' => $this->total($pending),
            'deposits' => CashDeposit::forLine(ActiveLine::current())->where('sales_id', auth()->id())->orderByDesc('id')->get(),
        ])->layout('layouts.sales');
    }
}

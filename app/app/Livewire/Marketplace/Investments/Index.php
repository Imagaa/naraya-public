<?php

namespace App\Livewire\Marketplace\Investments;

use App\Livewire\Marketplace\Concerns\HasMarketplaceShell;
use App\Models\InvestmentWalletEntry;
use App\Models\InvestmentWithdrawal;
use App\Support\ActiveLine;
use App\Support\Investment\Investments;
use App\Support\Investment\Portfolio;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Investasi page for a store: wallet, packages, buy more, history. */
class Index extends Component
{
    use HasMarketplaceShell, WithFileUploads;

    public int $buyQty = 1;

    public string $method = 'transfer';

    public $proof = null;

    public bool $agree = false;

    public bool $withdrawOpen = false;

    public ?int $amount = null;

    public string $bank = '';

    public string $accountNumber = '';

    public string $accountName = '';

    public ?string $flash = null;

    public function mount(): void
    {
        $last = InvestmentWithdrawal::where('buyer_id', auth()->id())->latest('id')->first();
        if ($last) {
            [$this->bank, $this->accountNumber, $this->accountName] = [$last->bank_name, $last->account_number, $last->account_name];
        }
    }

    /** The purchase runs in steps: 1 jumlah, 2 cara bayar, 3 bukti bayar, 4 konfirmasi. */
    public int $buyStep = 1;

    public function changeQty(int $by): void
    {
        $this->buyQty = max(1, min(1000, $this->buyQty + $by));
    }

    public function nextStep(Investments $investments): void
    {
        $this->resetErrorBag();
        match ($this->buyStep) {
            1 => $this->validate(['buyQty' => 'required|integer|min:1|max:1000']),
            2 => $this->validate(['method' => 'required|in:transfer']), // since 2026-10-12: transfer here, cash through the sales person
            3 => $this->checkPayment($investments),
            default => null,
        };
        $this->buyStep = min(4, $this->buyStep + 1);
    }

    public function prevStep(): void
    {
        $this->resetErrorBag();
        $this->buyStep = max(1, $this->buyStep - 1);
    }

    private function checkPayment(Investments $investments): void
    {
        if ($this->method === 'wallet') {
            if ($investments->balance(auth()->user()) < $this->buyQty * config('naraya.investment.package_price')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['method' => 'Saldo dompet tidak cukup. Pilih transfer atau QRIS.']);
            }

            return;
        }
        $this->validate(['proof' => 'required|image|max:8192'], ['proof.required' => 'Pilih foto bukti pembayaran dulu.']);
    }

    public function buy(Investments $investments): void
    {
        $this->resetErrorBag();
        $this->validate([
            'buyQty' => 'required|integer|min:1|max:1000',
            'method' => 'required|in:transfer',
            'proof' => 'nullable|image|max:8192',
            'agree' => 'accepted',
        ], ['agree.accepted' => 'Centang dulu bahwa Anda sudah membaca dan setuju dengan syaratnya.']);

        $p = $investments->buy(auth()->user(), $this->buyQty, $this->method, $this->method === 'wallet' ? null : $this->proof, ActiveLine::current()->value);
        $this->reset('buyQty', 'proof', 'agree', 'buyStep');
        $this->flash = $p->status === 'dibayar'
            ? 'Pembelian '.$p->number().' dibayar dari saldo. Paket aktif setelah diproses Naraya.'
            : 'Pembelian '.$p->number().' diterima. Admin akan memeriksa bukti pembayaran Anda.';
    }

    public function withdraw(Investments $investments): void
    {
        $this->resetErrorBag();
        $this->validate([
            'amount' => 'required|integer|min:1',
            'bank' => 'required|string|max:60',
            'accountNumber' => 'required|string|max:40',
            'accountName' => 'required|string|max:100',
        ], ['amount.required' => 'Isi nominal yang ditarik.']);

        $investments->requestWithdrawal(auth()->user(), (int) $this->amount, $this->bank, $this->accountNumber, $this->accountName);
        $this->reset('amount', 'withdrawOpen');
        $this->flash = 'Penarikan diajukan. Admin mentransfer paling lambat 1×24 jam hari kerja.';
    }

    public function render()
    {
        $store = auth()->user();

        return view('livewire.marketplace.investments.index', [
            'p' => Portfolio::for($store),
            'terms' => app(Investments::class)->termsFor($store),
            'transferTo' => config('naraya.investment.transfer_to'),
            // a preview of the chosen proof; a file that cannot be previewed just shows the icon
            'entries' => InvestmentWalletEntry::where('buyer_id', $store->id)->latest('id')->limit(30)->get(),
            'waiting' => InvestmentWithdrawal::where('buyer_id', $store->id)->where('status', 'menunggu')->latest('id')->get(),
            'cartCount' => $this->cartCount(),
        ])->layout('layouts.marketplace')->title('Investasi — Naraya');
    }
}

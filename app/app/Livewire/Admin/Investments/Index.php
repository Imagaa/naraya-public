<?php

namespace App\Livewire\Admin\Investments;

use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentWalletEntry;
use App\Models\InvestmentWithdrawal;
use App\Models\User;
use App\Support\BusinessDay;
use App\Support\Investment\Investments;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Investasi CRM: Ringkasan, Pembelian,
 * Penarikan, Investor. Naraya resells to a partner platform, so "dibayar"
 * purchases are money taken from stores that is not yet passed on.
 */
class Index extends Component
{
    use HasAdminShell, WithFileUploads, WithPagination;

    public const TABS = ['ringkasan' => 'Ringkasan', 'pembelian' => 'Pembelian', 'penarikan' => 'Penarikan', 'investor' => 'Investor'];

    #[Url]
    public string $tab = 'ringkasan';

    #[Url]
    public string $status = '';

    /** The row being acted on: ['activate'|'reject'|'transfer'|'rejectWithdrawal', id] */
    public ?string $acting = null;

    public ?int $actingId = null;

    public string $reason = '';

    public string $partnerRef = '';

    public $transferProof = null;

    public function mount(): void
    {
        $this->tab = array_key_exists($this->tab, self::TABS) ? $this->tab : 'ringkasan';
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'ringkasan';
        $this->reset('status', 'acting', 'actingId');
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function startAct(string $action, int $id): void
    {
        $this->resetErrorBag();
        $this->reset('reason', 'partnerRef', 'transferProof');
        [$this->acting, $this->actingId] = [$action, $id];
    }

    public function stopAct(): void
    {
        $this->reset('acting', 'actingId', 'reason', 'partnerRef', 'transferProof');
    }

    public function verify(Investments $inv, int $id): void
    {
        $inv->verify(InvestmentPurchase::findOrFail($id), auth()->user());
    }

    public function reject(Investments $inv): void
    {
        $this->resetErrorBag();
        $inv->reject(InvestmentPurchase::findOrFail($this->actingId), auth()->user(), $this->reason);
        $this->stopAct();
    }

    public function activate(Investments $inv): void
    {
        $this->resetErrorBag();
        $inv->activate(InvestmentPurchase::findOrFail($this->actingId), auth()->user(), $this->partnerRef);
        $this->stopAct();
    }

    public function transfer(Investments $inv): void
    {
        $this->resetErrorBag();
        $this->validate(['transferProof' => 'nullable|image|max:8192']);
        $inv->markTransferred(InvestmentWithdrawal::findOrFail($this->actingId), auth()->user(), $this->transferProof);
        $this->stopAct();
    }

    public function rejectWithdrawal(Investments $inv): void
    {
        $this->resetErrorBag();
        $inv->rejectWithdrawal(InvestmentWithdrawal::findOrFail($this->actingId), auth()->user(), $this->reason);
        $this->stopAct();
    }

    public function render()
    {
        $data = ['tabs' => self::TABS, 'stats' => $this->stats()];

        if ($this->tab === 'ringkasan') {
            $data['forward'] = InvestmentPurchase::where('status', 'dibayar')->with(['buyer:id,name', 'salesUser:id,name'])->oldest('id')->get();
            $data['proofs'] = InvestmentPurchase::where('status', 'menunggu_verifikasi')->with('buyer:id,name')->oldest('id')->get();
            $data['withdrawals'] = InvestmentWithdrawal::where('status', 'menunggu')->with('buyer:id,name')->oldest('id')->get();
            $data['schedule'] = $this->schedule();
        } elseif ($this->tab === 'pembelian') {
            $data['purchases'] = InvestmentPurchase::with(['buyer:id,name', 'salesUser:id,name'])
                ->when(array_key_exists($this->status, InvestmentPurchase::STATUS_LABELS), fn ($q) => $q->where('status', $this->status))
                ->latest('id')->paginate(25);
            $data['counts'] = InvestmentPurchase::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        } elseif ($this->tab === 'penarikan') {
            $data['withdrawalList'] = InvestmentWithdrawal::with(['buyer:id,name', 'processor:id,name'])
                ->when(array_key_exists($this->status, InvestmentWithdrawal::STATUS_LABELS), fn ($q) => $q->where('status', $this->status))
                ->latest('id')->paginate(25);
        } else {
            $data['investors'] = $this->investors();
        }

        return view('livewire.admin.investments.index', $data)->layout('layouts.admin');
    }

    private function stats(): array
    {
        $by = fn (string $status) => InvestmentPurchase::where('status', $status);
        $monthStart = BusinessDay::today()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        return [
            'active_packages' => (int) $by('aktif')->sum('quantity'),
            'active_stores' => (int) $by('aktif')->distinct('buyer_id')->count('buyer_id'),
            'bought_amount' => (int) InvestmentPurchase::whereIn('status', ['dibayar', 'aktif', 'selesai'])->sum('amount'),
            'bought_packages' => (int) InvestmentPurchase::whereIn('status', ['dibayar', 'aktif', 'selesai'])->sum('quantity'),
            'month_income' => (int) InvestmentWalletEntry::where('type', 'payout')
                ->whereBetween('created_at', [BusinessDay::boundsFor($monthStart)[0], BusinessDay::boundsFor($monthEnd)[1]])->sum('amount')
                + (int) $by('aktif')->whereDate('next_payout_on', '<=', $monthEnd->toDateString())->get()->sum(fn ($p) => $p->monthlyTotal()),
            'wallets' => (int) InvestmentWalletEntry::sum('amount'),
            'forward_packages' => (int) $by('dibayar')->sum('quantity'),
            'forward_amount' => (int) $by('dibayar')->sum('amount'),
            'proofs_waiting' => $by('menunggu_verifikasi')->count(),
            'withdrawals_waiting' => InvestmentWithdrawal::where('status', 'menunggu')->count(),
            'withdrawals_amount' => (int) InvestmentWithdrawal::where('status', 'menunggu')->sum('amount'),
        ];
    }

    /** Money the program pays out in the next 30 days (monthly income, and principal on the 12th). */
    private function schedule(): array
    {
        $until = BusinessDay::today()->addDays(30)->toDateString();
        $due = InvestmentPurchase::where('status', 'aktif')->whereDate('next_payout_on', '<=', $until)->get();
        $final = $due->filter(fn ($p) => $p->months_paid + 1 >= $p->months);

        return [
            'income' => (int) $due->sum(fn ($p) => $p->monthlyTotal()),
            'income_packages' => (int) $due->sum('quantity'),
            'principal' => (int) $final->sum('amount'),
            'principal_packages' => (int) $final->sum('quantity'),
        ];
    }

    private function investors()
    {
        $purchases = InvestmentPurchase::whereIn('status', ['dibayar', 'aktif', 'selesai', 'menunggu_verifikasi'])
            ->get()->groupBy('buyer_id');
        $wallets = InvestmentWalletEntry::groupBy('buyer_id')->select('buyer_id', DB::raw('SUM(amount) as balance'))->pluck('balance', 'buyer_id');
        $income = InvestmentWalletEntry::where('type', 'payout')->groupBy('buyer_id')->select('buyer_id', DB::raw('SUM(amount) as income'))->pluck('income', 'buyer_id');

        return User::whereIn('id', $purchases->keys())->with('buyerProfile.salesUser:id,name')->get()
            ->map(fn (User $u) => [
                'store' => $u,
                'active' => (int) $purchases[$u->id]->where('status', 'aktif')->sum('quantity'),
                'capital' => (int) $purchases[$u->id]->whereIn('status', ['dibayar', 'aktif', 'menunggu_verifikasi'])->sum('amount'),
                'balance' => (int) ($wallets[$u->id] ?? 0),
                'income' => (int) ($income[$u->id] ?? 0),
                'last' => $purchases[$u->id]->max('created_at'),
            ])
            ->sortByDesc('capital')->values();
    }
}

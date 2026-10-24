<?php

namespace App\Livewire\Admin\Reconciliation;

use App\Enums\Role;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\CashDeposit;
use App\Models\SalesNote;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\BusinessDay;
use App\Support\Sales\Reconciliation;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Pencocokan: goods taken by each sales person against the money returned. */
class Index extends Component
{
    use HasAdminShell;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(as: 'sales')]
    public ?int $salesId = null;

    public function mount(): void
    {
        $this->from = $this->from ?: BusinessDay::today()->startOfMonth()->toDateString();
        $this->to = $this->to ?: BusinessDay::today()->toDateString();
    }

    public function show(?int $salesId): void
    {
        $this->salesId = $salesId;
    }

    public function render(Reconciliation $reconciliation)
    {
        $line = ActiveLine::current();
        [$from, $to] = [$this->date($this->from), $this->date($this->to)];
        $range = [BusinessDay::boundsFor($from)[0], BusinessDay::boundsFor($to)[1]];
        $selected = $this->salesId ? User::where('role', Role::Sales)->find($this->salesId) : null;

        return view('livewire.admin.reconciliation.index', [
            'line' => $line,
            'rows' => $reconciliation->rows($line, $from, $to),
            'selected' => $selected,
            'notes' => $selected ? SalesNote::forLine($line)->where('sales_id', $selected->id)->whereBetween('created_at', $range)
                ->with(['buyer:id,name', 'deposit:id,status'])->latest('id')->get() : collect(),
            'transfers' => $selected ? StockTransfer::forLine($line)->where('sales_id', $selected->id)->whereBetween('created_at', $range)
                ->with('items.product:id,name,unit,pieces_per_unit')->latest('id')->get() : collect(),
            'deposits' => $selected ? CashDeposit::forLine($line)->where('sales_id', $selected->id)->whereBetween('created_at', $range)
                ->latest('id')->get() : collect(),
        ])->layout('layouts.admin');
    }

    private function date(string $value): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return BusinessDay::today();
        }
    }
}

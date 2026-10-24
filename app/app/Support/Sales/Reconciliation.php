<?php

namespace App\Support\Sales;

use App\Enums\BusinessLine;
use App\Enums\CashDepositStatus;
use App\Enums\NotePaymentMethod;
use App\Enums\ProofStatus;
use App\Enums\Role;
use App\Enums\StockTransferKind;
use App\Models\InvestmentPurchase;
use App\Models\Product;
use App\Models\SalesNote;
use App\Models\User;
use App\Support\BusinessDay;
use App\Support\Stock\CarriedStock;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pencocokan: per sales person, the goods that
 * moved in a period (valued at the snapshot price) against the money that
 * came back. Kewajiban tunai = cash not yet deposited + rejected transfer/QRIS
 * + stock-check shortfall; zero means "Cocok".
 */
final class Reconciliation
{
    private const SETTLED = [CashDepositStatus::Verified, CashDepositStatus::Resolved];

    /** @return Collection<int, array<string, mixed>> one row per sales person with activity or carried stock */
    public function rows(BusinessLine $line, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $range = [BusinessDay::boundsFor($from)[0], BusinessDay::boundsFor($to)[1]];

        // ponytail: a handful of queries per sales person; aggregate in SQL if the team grows past a few dozen
        return User::where('role', Role::Sales)->orderBy('name')->get()
            ->map(fn (User $sales) => $this->row($sales, $line, $range))
            ->filter(fn (array $r) => $r['taken'] || $r['returned'] || $r['sold'] || $r['shortfall'] || $r['carried'] || $r['cash'] || $r['nonCashPending'] || $r['investCash'])
            ->values();
    }

    /** One sales person's row for a period (WIB days). */
    public function forSales(User $sales, BusinessLine $line, CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->row($sales, $line, [BusinessDay::boundsFor($from)[0], BusinessDay::boundsFor($to)[1]]);
    }

    /** @param array{0: CarbonInterface, 1: CarbonInterface} $range */
    private function row(User $sales, BusinessLine $line, array $range): array
    {
        $moved = DB::table('stock_transfer_items')
            ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->where('stock_transfers.sales_id', $sales->id)->where('stock_transfers.line', $line->value)
            ->whereBetween('stock_transfers.created_at', $range)
            ->groupBy('stock_transfers.kind')
            ->selectRaw('stock_transfers.kind as kind, SUM(stock_transfer_items.value) as value')
            ->pluck('value', 'kind')->map(fn ($v) => (int) $v);

        $notes = SalesNote::forLine($line)->where('sales_id', $sales->id)->whereBetween('created_at', $range)
            ->with('deposit:id,status')->get(['id', 'payment_method', 'proof_status', 'total', 'deposit_total', 'cash_deposit_id']);
        $cash = $notes->where('payment_method', NotePaymentMethod::Cash);
        $nonCash = fn (ProofStatus $status) => (int) $notes->where('proof_status', $status)->sum('total');

        $carried = CarriedStock::for($sales, $line);
        $products = Product::whereIn('id', $carried->keys())->get()->keyBy('id');

        $row = [
            'sales' => $sales,
            'taken' => $moved[StockTransferKind::Ambil->value] ?? 0,
            'returned' => $moved[StockTransferKind::Retur->value] ?? 0,
            'sold' => (int) $notes->sum('total'),
            'shortfall' => $moved[StockTransferKind::Opname->value] ?? 0,
            'carried' => (int) $carried->sum(fn (int $qty, int $id) => $products[$id]?->valueOf($qty) ?? 0),
            'cash' => (int) $cash->sum('deposit_total'), // owed at the deposit price
            'cashDeposited' => (int) $cash->filter(fn (SalesNote $n) => in_array($n->deposit?->status, self::SETTLED, true))->sum('deposit_total'),
            'nonCashVerified' => $nonCash(ProofStatus::Verified),
            'nonCashPending' => $nonCash(ProofStatus::Pending),
            'nonCashRejected' => $nonCash(ProofStatus::Rejected),
        ];
        $invest = $line === \App\Support\ActiveLine::investmentCashLine()
            ? InvestmentPurchase::where('sales_id', $sales->id)->where('payment_method', 'cash_sales')->where('status', '!=', 'ditolak')
                ->whereBetween('created_at', $range)->with('deposit:id,status')->get(['id', 'amount', 'cash_deposit_id'])
            : collect();
        $row['investCash'] = (int) $invest->sum('amount');
        $row['investCashOpen'] = (int) $invest->reject(fn ($i) => in_array($i->deposit?->status, self::SETTLED, true))->sum('amount');
        $row['cashOpen'] = $row['cash'] - $row['cashDeposited'];
        $row['owed'] = $row['cashOpen'] + $row['nonCashRejected'] + $row['shortfall'] + $row['investCashOpen'];
        $row['matched'] = $row['owed'] === 0;

        return $row;
    }
}

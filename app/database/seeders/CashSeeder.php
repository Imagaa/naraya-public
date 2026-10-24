<?php

namespace Database\Seeders;

use App\Enums\CashBookEntryType;
use App\Enums\CashCollectionStatus;
use App\Enums\CashDepositStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CashBookEntry;
use App\Models\CashCollection;
use App\Models\CashDeposit;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class CashSeeder extends Seeder
{
    public function run(): void
    {
        $salesUsers = User::where('role', Role::Sales)->get();
        $cashOrders = Order::where('status', OrderStatus::Completed)->where('payment_method', 'cash')->get();

        // Every completed cash order gets a collection.
        $collections = $cashOrders->map(function (Order $order) use ($salesUsers) {
            return CashCollection::create([
                'order_id' => $order->id,
                'sales_id' => $order->sales_id ?? $salesUsers->random()->id,
                'amount' => $order->total,
                'status' => CashCollectionStatus::Held,
                'collected_at' => $order->completed_at,
            ]);
        });

        // Group ~80% of collections into deposits per sales user, leave the rest Held.
        // The seeder test asserts a Held collection deterministically exists, so the
        // first one is explicitly reserved and excluded from the deposit split rather
        // than relying on the 20% remainder alone.
        $reservedHeldId = $collections->first()?->id;
        $depositable = $collections->reject(fn ($c) => $c->id === $reservedHeldId);
        $toDeposit = $depositable->shuffle()->take((int) ($depositable->count() * 0.8));

        foreach ($toDeposit->groupBy('sales_id') as $salesId => $salesCollections) {
            $expected = $salesCollections->sum('amount');

            $deposit = CashDeposit::create([
                'sales_id' => $salesId,
                'expected_amount' => $expected,
                'claimed_amount' => $expected,
                'status' => CashDepositStatus::Verified,
                'created_at' => $salesCollections->max('collected_at'),
            ]);

            CashCollection::whereIn('id', $salesCollections->pluck('id'))
                ->update(['status' => CashCollectionStatus::Deposited, 'cash_deposit_id' => $deposit->id]);

            CashBookEntry::create([
                'type' => CashBookEntryType::Pemasukan,
                'amount' => $deposit->claimed_amount,
                'date' => $deposit->created_at->toDateString(),
                'reference' => 'Setoran #'.$deposit->id,
                'kind' => 'rekap',
                'source' => 'setoran_sales',
            ]);
        }

        // One explicit disputed deposit (claimed != expected).
        $disputedSales = $salesUsers->random();
        $disputed = CashDeposit::create([
            'sales_id' => $disputedSales->id,
            'expected_amount' => 250000,
            'claimed_amount' => 200000,
            'status' => CashDepositStatus::Disputed,
            'proof_note' => 'Ada uang yang hilang saat pengiriman terakhir',
        ]);

        // One explicit resolved deposit.
        CashDeposit::create([
            'sales_id' => $salesUsers->random()->id,
            'expected_amount' => 180000,
            'claimed_amount' => 150000,
            'status' => CashDepositStatus::Resolved,
            'resolution_note' => 'Selisih dikonfirmasi hilang, dicatat sebagai kerugian',
            'resolved_by' => User::where('role', Role::Admin)->first()?->id,
            'resolved_at' => now()->subDays(3),
        ]);
    }
}

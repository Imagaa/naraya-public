<?php

namespace App\Support\Sales;

use App\Enums\CashCollectionStatus;
use App\Enums\NotePaymentMethod;
use App\Models\CashCollection;
use App\Models\InvestmentPurchase;
use App\Models\SalesNote;
use App\Models\User;

/**
 * Cash a sales person owes the admin and has not deposited yet: order collections, cash notes (at the deposit price,
 * deposit_total — the sales person's cut stays with them), cash investments (every line).
 */
final class CashHeld
{
    public static function for(User $sales): int
    {
        return (int) CashCollection::where('sales_id', $sales->id)->where('status', CashCollectionStatus::Held)->sum('amount')
            + (int) SalesNote::where('sales_id', $sales->id)->where('payment_method', NotePaymentMethod::Cash)->whereNull('cash_deposit_id')->sum('deposit_total')
            + (int) InvestmentPurchase::where('sales_id', $sales->id)->where('payment_method', 'cash_sales')->whereNull('cash_deposit_id')->where('status', '!=', 'ditolak')->sum('amount');
    }
}

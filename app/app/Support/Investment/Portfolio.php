<?php

namespace App\Support\Investment;

use App\Models\InvestmentPurchase;
use App\Models\InvestmentWalletEntry;
use App\Models\User;
use App\Support\BusinessDay;

/** What one store sees of its investment: the marketplace card and the Investasi page. */
final class Portfolio
{
    public static function for(User $store): array
    {
        $purchases = InvestmentPurchase::where('buyer_id', $store->id)->latest('id')->get();
        $live = $purchases->whereIn('status', ['menunggu_verifikasi', 'dibayar', 'aktif']);
        $active = $purchases->where('status', 'aktif');

        $monthStart = BusinessDay::today()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $creditedThisMonth = (int) InvestmentWalletEntry::where('buyer_id', $store->id)->where('type', 'payout')
            ->whereBetween('created_at', [BusinessDay::boundsFor($monthStart)[0], BusinessDay::boundsFor($monthEnd)[1]])->sum('amount');
        $stillDueThisMonth = (int) $active->filter(fn ($p) => $p->next_payout_on && $p->next_payout_on->betweenIncluded($monthStart, $monthEnd))
            ->sum(fn ($p) => $p->monthlyTotal());

        return [
            'has_any' => $purchases->where('status', '!=', 'ditolak')->isNotEmpty(),
            'balance' => app(Investments::class)->balance($store),
            'this_month' => $creditedThisMonth + $stillDueThisMonth,
            'next_payout' => $active->pluck('next_payout_on')->filter()->sort()->first(),
            'active_packages' => (int) $active->sum('quantity'),
            'processing_packages' => (int) $purchases->whereIn('status', ['menunggu_verifikasi', 'dibayar'])->sum('quantity'),
            'running_capital' => (int) $live->sum('amount'),
            'total_income' => (int) InvestmentWalletEntry::where('buyer_id', $store->id)->where('type', 'payout')->sum('amount'),
            'first_purchase' => $purchases->where('status', '!=', 'ditolak')->min('created_at'),
            'purchases' => $purchases,
        ];
    }
}

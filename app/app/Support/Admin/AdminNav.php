<?php

namespace App\Support\Admin;

use App\Enums\BusinessLine;
use App\Support\ActiveLine;
use Illuminate\Support\Facades\Route;

/**
 * The admin sidebar: groups of links and their badge counts. Each badge sums
 * the dashboard work queues behind it; the badge
 * counts also feed GET /nav-badges, which the sidebar polls.
 */
final class AdminNav
{
    private const QUEUES = [
        'Nota penjualan' => ['note-proofs'],
        'Pesanan' => ['payment-review', 'new-orders', 'unassigned', 'ready-to-ship', 'awaiting-close', 'stuck-gudang', 'stuck-sales', 'refunds'],
        'Retur & Komplain' => ['complaints'],
        'Toko' => ['registrations'],
        'Setoran sales' => ['deposits'],
        'Investasi' => ['investment-proofs', 'investment-forward', 'investment-withdrawals'],
        'Produk & Stok' => ['stock'],
        'Sales & Area' => ['phone-change'],
        'Penukaran Hadiah' => ['redemptions-decision', 'redemptions-handover'],
    ];

    /** @return array<string, int> sidebar label => count */
    public static function badges(): array
    {
        return array_map(fn (array $keys) => WorkQueues::count(...$keys), self::QUEUES);
    }

    /**
     * Sidebar groups: [label, links], each link
     * [route, active pattern, label, lucide icon, badge]. Links whose route doesn't
     * exist yet are left out. While the marketplace is on hold its pages move into
     * their own group, shown in the Naraya line only (the marketplace is Naraya's).
     *
     * @return list<array{0: string, 1: list<array{0: string, 1: string, 2: string, 3: string, 4: int}>}>
     */
    public static function groups(): array
    {
        $b = self::badges();
        $marketplaceOpen = (bool) config('naraya.marketplace_purchasing_open'); // orders and loyalty only matter while stores can buy online

        $orders = ['admin.orders.index', 'admin.orders.*', 'Pesanan', 'shopping-cart', $b['Pesanan']];
        $complaints = ['admin.complaints.index', 'admin.complaints.*', 'Retur & Komplain', 'message-circle-warning', $b['Retur & Komplain']];
        $redemptions = ['admin.redemptions.index', 'admin.redemptions.*', 'Penukaran Hadiah', 'repeat', $b['Penukaran Hadiah']];
        $rewards = ['admin.rewards.index', 'admin.rewards.*', 'Katalog Hadiah', 'gift', 0];

        $groups = [
            ['', [['admin.dashboard', 'admin.dashboard', 'Beranda', 'house', 0]]],
            ['Penjualan', array_merge(
                [['admin.notes.index', 'admin.notes.*', 'Nota penjualan', 'receipt-text', $b['Nota penjualan']]],
                $marketplaceOpen ? [$orders, $complaints] : [],
                [['admin.buyers.index', 'admin.buyers.*', 'Toko', 'store', $b['Toko']]],
            )],
            ['Gudang', [
                ['admin.products.index', 'admin.products.*', 'Produk & Stok', 'package', $b['Produk & Stok']],
                ['admin.goods-in.index', 'admin.goods-in.*', 'Barang masuk', 'package-plus', 0],
                ['admin.transfers.index', 'admin.transfers.*', 'Pengambilan sales', 'arrow-left-right', 0],
                ['admin.carried.index', 'admin.carried.*', 'Stok bawaan sales', 'backpack', 0],
            ]],
            ['Keuangan', [
                ['admin.deposits.index', 'admin.deposits.*', 'Setoran sales', 'wallet', $b['Setoran sales']],
                ['admin.cash-book.index', 'admin.cash-book.*', 'Buku Kas', 'book-open', 0],
                ['admin.reconciliation.index', 'admin.reconciliation.*', 'Pencocokan', 'scale', 0],
                ['admin.investments.index', 'admin.investments.*', 'Investasi', 'trending-up', $b['Investasi']],
            ]],
            ['Tim Sales', [
                ['admin.sales.index', 'admin.sales.*|admin.areas.*', 'Sales & Area', 'users', $b['Sales & Area']],
                ['admin.tracking.index', 'admin.tracking.*', 'Perjalanan sales', 'navigation', 0],
                ['admin.attendances.index', 'admin.attendances.*', 'Absensi', 'clock', 0],
                ['admin.routes.index', 'admin.routes.*', 'Rute sales', 'route', 0],
            ]],
        ];

        if ($marketplaceOpen) {
            $groups[] = ['Loyalty', [$redemptions, $rewards]];
        } elseif (ActiveLine::current() === BusinessLine::Naraya) {
            $groups[] = ['Marketplace (ditahan)', [$orders, $complaints, $redemptions, $rewards]];
        }

        $groups[] = ['', [['admin.reports.index', 'admin.reports.*', 'Laporan', 'chart-column', 0]]];

        return array_values(array_filter(array_map(
            fn (array $group) => [$group[0], array_values(array_filter($group[1], fn (array $link) => Route::has($link[0])))],
            $groups,
        ), fn (array $group) => $group[1] !== []));
    }
}

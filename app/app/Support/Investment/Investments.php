<?php

namespace App\Support\Investment;

use App\Models\BuyerProfile;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentWalletEntry;
use App\Models\InvestmentWithdrawal;
use App\Models\User;
use App\Notifications\AdminNotice;
use App\Support\BusinessDay;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Investasi Naraya. A purchase is paid
 * by transfer/QRIS proof (admin verifies), from the wallet, or in cash to the
 * store's sales person; admin then passes it on to the partner platform,
 * which activates it. Payment n lands on the activation date + n months; the
 * last one also returns the principal. A store's first purchase skips month 1:
 * its payments start in month 2 and run one time fewer, so every package still
 * ends in the same month. The wallet is a signed ledger.
 */
final class Investments
{
    public const MIN_WITHDRAWAL = 10000;

    public function terms(): array
    {
        return config('naraya.investment');
    }

    /**
     * The terms a store's next purchase gets: 'months' = how many payments, 'skip' = months before the first,
     * 'term' = month the principal comes back. A store that never bought (or a guest) gets the first-purchase terms.
     */
    public function termsFor(?User $store): array
    {
        $t = $this->terms();
        $first = ! $store || ! InvestmentPurchase::where('buyer_id', $store->id)->where('status', '!=', 'ditolak')->exists();

        return ['first' => $first, 'skip' => $first ? 1 : 0, 'months' => $t['months'] - ($first ? 1 : 0), 'term' => $t['months']] + $t;
    }

    public function balance(User $store): int
    {
        return (int) InvestmentWalletEntry::where('buyer_id', $store->id)->sum('amount');
    }

    /** $method: transfer | qris (proof required) | wallet. $source: the storefront it was bought from. */
    public function buy(User $store, int $quantity, string $method, ?UploadedFile $proof = null, string $source = 'naraya'): InvestmentPurchase
    {
        $this->assertQuantity($quantity);
        if (! in_array($method, ['transfer', 'qris', 'wallet'], true)) {
            throw ValidationException::withMessages(['method' => 'Pilih cara bayar.']);
        }
        if ($method !== 'wallet' && ! $proof) {
            throw ValidationException::withMessages(['proof' => 'Foto bukti '.InvestmentPurchase::METHOD_LABELS[$method].' wajib dilampirkan.']);
        }
        $path = $proof?->store('investment-proofs', 's3');

        $purchase = DB::transaction(function () use ($store, $quantity, $method, $path, $source) {
            $purchase = $this->newPurchase($store, $quantity, [
                'payment_method' => $method,
                'source' => in_array($source, ['naraya', 'toys'], true) ? $source : 'naraya',
                'proof_path' => $path,
                'status' => $method === 'wallet' ? 'dibayar' : 'menunggu_verifikasi',
            ]);

            if ($method === 'wallet') {
                $this->lockWallet($store);
                if ($this->balance($store) < $purchase->amount) {
                    throw ValidationException::withMessages(['method' => 'Saldo dompet tidak cukup untuk '.$quantity.' paket.']);
                }
                $this->entry($store, 'purchase', -$purchase->amount, 'Beli '.$quantity.' paket · '.$purchase->number(), purchase: $purchase);
            }

            return $purchase;
        });

        AdminNotice::toAdmins('Pembelian investasi', $store->name.' membeli '.$quantity.' paket ('.$purchase->methodLabel().')', route('admin.investments.index', ['tab' => 'pembelian'], absolute: false), 'trending-up');

        return $purchase;
    }

    /** Cash handed to the store's own sales person; it joins their Setoran. */
    public function buyViaSales(User $sales, int $storeId, int $quantity): InvestmentPurchase
    {
        $this->assertQuantity($quantity);
        if (! BuyerProfile::where('user_id', $storeId)->where('sales_user_id', $sales->id)->exists()) {
            throw ValidationException::withMessages(['storeId' => 'Toko ini tidak ditugaskan ke Anda.']);
        }

        $purchase = $this->newPurchase(User::findOrFail($storeId), $quantity, [
            'payment_method' => 'cash_sales', 'source' => 'sales', 'sales_id' => $sales->id, 'status' => 'dibayar',
        ]);
        AdminNotice::toAdmins('Pembelian investasi lewat sales', $sales->name.' mencatat '.$quantity.' paket untuk '.$purchase->buyer->name.' (tunai)', route('admin.investments.index', ['tab' => 'pembelian'], absolute: false), 'trending-up');

        return $purchase;
    }

    public function verify(InvestmentPurchase $purchase, User $admin): void
    {
        $this->assertStatus($purchase, 'menunggu_verifikasi', 'Bukti pembelian ini sudah diperiksa.');
        $purchase->update(['status' => 'dibayar', 'verified_by' => $admin->id, 'verified_at' => now()]);
    }

    public function reject(InvestmentPurchase $purchase, User $admin, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Tuliskan alasan penolakan.']);
        }
        $this->assertStatus($purchase, 'menunggu_verifikasi', 'Bukti pembelian ini sudah diperiksa.');
        $purchase->update(['status' => 'ditolak', 'reject_reason' => trim($reason), 'verified_by' => $admin->id, 'verified_at' => now()]);
    }

    /** Admin passed it on to the partner platform: it is active from today (WIB). */
    public function activate(InvestmentPurchase $purchase, User $admin, ?string $partnerRef = null): void
    {
        $this->assertStatus($purchase, 'dibayar', 'Hanya pembelian yang sudah dibayar yang bisa disalurkan.');
        $purchase->update([
            'status' => 'aktif',
            'activated_by' => $admin->id,
            'activated_at' => now(),
            'partner_ref' => trim((string) $partnerRef) ?: null,
            'next_payout_on' => BusinessDay::today()->addMonthsNoOverflow($purchase->skipped_months + 1)->toDateString(),
        ]);
    }

    /** Credit every payment that has fallen due (WIB day); safe to run any number of times. */
    public function payDue(?CarbonInterface $today = null): int
    {
        $today = ($today ? Carbon::instance($today) : BusinessDay::today())->toDateString();
        $paid = 0;
        $sum = 0;
        $stores = [];
        $finished = [];

        InvestmentPurchase::where('status', 'aktif')->whereDate('next_payout_on', '<=', $today)->pluck('id')
            ->each(function (int $id) use ($today, &$paid, &$sum, &$stores, &$finished) {
                DB::transaction(function () use ($id, $today, &$paid, &$sum, &$stores, &$finished) {
                    $p = InvestmentPurchase::whereKey($id)->lockForUpdate()->first();
                    while ($p->status === 'aktif' && $p->next_payout_on && $p->next_payout_on->toDateString() <= $today) {
                        $n = $p->months_paid + 1;
                        $this->entry($p->buyer, 'payout', $p->monthlyTotal(), 'Pendapatan bulan ke-'.$n.' · '.$p->number(), purchase: $p, at: $p->next_payout_on);
                        $last = $n >= $p->months;
                        if ($last) {
                            $this->entry($p->buyer, 'principal', $p->amount, 'Modal kembali · '.$p->number(), purchase: $p, at: $p->next_payout_on);
                        }
                        $activation = Carbon::parse($p->activated_at)->timezone(BusinessDay::zone())->startOfDay();
                        $p->update([
                            'months_paid' => $n,
                            'status' => $last ? 'selesai' : 'aktif',
                            'completed_at' => $last ? now() : null,
                            'next_payout_on' => $last ? null : $activation->copy()->addMonthsNoOverflow($p->skipped_months + $n + 1)->toDateString(),
                        ]);
                        $paid++;
                        $sum += $p->monthlyTotal();
                        $stores[$p->buyer_id] = true;
                        if ($last) {
                            $finished[] = $p;
                        }
                    }
                });
            });

        // the owner hears the day's payouts as one sum, and every package that just ended
        if ($paid > 0) {
            AdminNotice::toAdmins('Bagi hasil dikreditkan', $paid.' pembayaran · Rp '.number_format($sum, 0, ',', '.').' ke '.count($stores).' toko', route('admin.investments.index', absolute: false), 'trending-up', 'success');
        }
        foreach ($finished as $p) {
            AdminNotice::toAdmins('Paket investasi selesai', $p->buyer?->name.' · '.$p->number().' · modal Rp '.number_format($p->amount, 0, ',', '.').' kembali', route('admin.investments.index', absolute: false), 'trending-up');
        }

        return $paid;
    }

    public function requestWithdrawal(User $store, int $amount, string $bank, string $number, string $name): InvestmentWithdrawal
    {
        if ($amount < self::MIN_WITHDRAWAL) {
            throw ValidationException::withMessages(['amount' => 'Penarikan minimal Rp'.number_format(self::MIN_WITHDRAWAL, 0, ',', '.').'.']);
        }
        foreach (['bank' => $bank, 'number' => $number, 'name' => $name] as $field => $value) {
            if (trim($value) === '') {
                throw ValidationException::withMessages([$field => 'Lengkapi data rekening.']);
            }
        }

        $withdrawal = DB::transaction(function () use ($store, $amount, $bank, $number, $name) {
            $this->lockWallet($store);
            if ($this->balance($store) < $amount) {
                throw ValidationException::withMessages(['amount' => 'Saldo dompet tidak cukup.']);
            }
            $w = InvestmentWithdrawal::create([
                'buyer_id' => $store->id, 'amount' => $amount, 'status' => 'menunggu',
                'bank_name' => trim($bank), 'account_number' => preg_replace('/\s+/', '', $number), 'account_name' => trim($name),
            ]);
            $this->entry($store, 'withdrawal', -$amount, 'Penarikan ke '.$w->maskedAccount(), withdrawal: $w);

            return $w;
        });
        AdminNotice::toAdmins('Penarikan dana investasi', $store->name.' menarik Rp'.number_format($amount, 0, ',', '.'), route('admin.investments.index', ['tab' => 'penarikan'], absolute: false), 'wallet', 'warning');

        return $withdrawal;
    }

    public function markTransferred(InvestmentWithdrawal $w, User $admin, ?UploadedFile $proof): void
    {
        if (! $proof) {
            throw ValidationException::withMessages(['transferProof' => 'Unggah foto bukti transfer.']);
        }
        $this->assertWithdrawalWaiting($w);
        $w->update(['status' => 'ditransfer', 'proof_path' => $proof->store('investment-withdrawals', 's3'), 'processed_by' => $admin->id, 'processed_at' => now()]);
    }

    public function rejectWithdrawal(InvestmentWithdrawal $w, User $admin, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Tuliskan alasan penolakan.']);
        }
        $this->assertWithdrawalWaiting($w);
        DB::transaction(function () use ($w, $admin, $reason) {
            $w->update(['status' => 'ditolak', 'reject_reason' => trim($reason), 'processed_by' => $admin->id, 'processed_at' => now()]);
            $this->entry($w->buyer, 'withdrawal_refund', $w->amount, 'Penarikan ditolak, dana kembali: '.trim($reason), withdrawal: $w);
        });
    }

    // ponytail: "first" is fixed when the purchase is made; if that first one is later rejected, a second one
    // bought meanwhile keeps the full 12 payments. Re-check at activation if that ever matters.
    private function newPurchase(User $store, int $quantity, array $attributes): InvestmentPurchase
    {
        $t = $this->termsFor($store);

        return InvestmentPurchase::create($attributes + [
            'buyer_id' => $store->id,
            'quantity' => $quantity,
            'unit_price' => $t['package_price'],
            'monthly_return' => $t['monthly_return'],
            'months' => $t['months'],
            'skipped_months' => $t['skip'],
            'amount' => $quantity * $t['package_price'],
        ]);
    }

    private function entry(User $store, string $type, int $amount, string $note, ?InvestmentPurchase $purchase = null, ?InvestmentWithdrawal $withdrawal = null, ?CarbonInterface $at = null): void
    {
        $entry = new InvestmentWalletEntry([
            'buyer_id' => $store->id, 'type' => $type, 'amount' => $amount, 'note' => $note,
            'investment_purchase_id' => $purchase?->id, 'investment_withdrawal_id' => $withdrawal?->id,
        ]);
        if ($at) {
            // a catch-up payout is dated on its own due day (09:00 WIB), so history reads by month
            $entry->created_at = Carbon::parse($at->format('Y-m-d').' 09:00', BusinessDay::zone())->utc()->min(now());
        }
        $entry->save();
    }

    /** Serialises wallet changes per store. */
    private function lockWallet(User $store): void
    {
        User::whereKey($store->id)->lockForUpdate()->first();
    }

    private function assertQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > 1000) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah paket 1–1.000.']);
        }
    }

    private function assertStatus(InvestmentPurchase $purchase, string $status, string $message): void
    {
        if ($purchase->status !== $status) {
            throw ValidationException::withMessages(['purchase' => $message]);
        }
    }

    private function assertWithdrawalWaiting(InvestmentWithdrawal $w): void
    {
        if ($w->status !== 'menunggu') {
            throw ValidationException::withMessages(['withdrawal' => 'Penarikan ini sudah diproses.']);
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A store's purchase of investment packages; status menunggu_verifikasi | dibayar | aktif | selesai | ditolak. */
class InvestmentPurchase extends Model
{
    public const STATUS_LABELS = [
        'menunggu_verifikasi' => 'Menunggu verifikasi',
        'dibayar' => 'Sedang diproses',
        'aktif' => 'Aktif',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
    ];

    public const METHOD_LABELS = ['transfer' => 'Transfer', 'qris' => 'QRIS', 'wallet' => 'Saldo dompet', 'cash_sales' => 'Tunai lewat sales'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'next_payout_on' => 'date',
        ];
    }

    public function number(): string
    {
        return 'INV-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function methodLabel(): string
    {
        return self::METHOD_LABELS[$this->payment_method] ?? $this->payment_method;
    }

    public function monthlyTotal(): int
    {
        return $this->quantity * $this->monthly_return;
    }

    public function proofUrl(): ?string
    {
        return $this->proof_path ? Storage::disk('s3')->url($this->proof_path) : null;
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(CashDeposit::class, 'cash_deposit_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A store's request to take money out of its investment wallet; admin transfers it by hand. */
class InvestmentWithdrawal extends Model
{
    public const STATUS_LABELS = ['menunggu' => 'Menunggu transfer', 'ditransfer' => 'Ditransfer', 'ditolak' => 'Ditolak'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function maskedAccount(): string
    {
        return $this->bank_name.' •••• '.substr($this->account_number, -4);
    }

    public function proofUrl(): ?string
    {
        return $this->proof_path ? Storage::disk('s3')->url($this->proof_path) : null;
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}

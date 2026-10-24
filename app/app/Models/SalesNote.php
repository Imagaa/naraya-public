<?php

namespace App\Models;

use App\Enums\NotePaymentMethod;
use App\Enums\ProofStatus;
use App\Models\Concerns\HasBusinessLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** A sales person's outright sale to one store in one line. Written by App\Support\Sales\SalesNotes only. */
class SalesNote extends Model
{
    use HasBusinessLine;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payment_method' => NotePaymentMethod::class,
            'proof_status' => ProofStatus::class,
            'proof_reviewed_at' => 'datetime',
            'total' => 'integer',
        ];
    }

    /** NJ-N00012 (Naraya) / NJ-T00003 (Naraya Toys) */
    public function number(): string
    {
        return 'NJ-'.$this->line->initial().str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesNoteItem::class);
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(CashDeposit::class, 'cash_deposit_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proof_reviewed_by');
    }

    public function proofUrl(): ?string
    {
        return $this->proof_path ? Storage::disk('s3')->url($this->proof_path) : null;
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'price' => 'integer', 'value' => 'integer'];
    }

    /** "Mobil-mobilan 2 lembar 5 pcs" — quantity is in pcs. */
    public function label(): string
    {
        return ($this->product?->name ?? '?').' '.($this->product?->qtyLabel($this->quantity) ?? $this->quantity.' pcs');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

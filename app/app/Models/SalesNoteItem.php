<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesNoteItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'price' => 'integer', 'subtotal' => 'integer'];
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(SalesNote::class, 'sales_note_id');
    }

    /** "2 lembar 5 pcs" — quantity is in pcs. */
    public function qtyLabel(): string
    {
        return $this->product?->qtyLabel($this->quantity) ?? $this->quantity.' pcs';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

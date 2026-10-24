<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One change to the goods of ours at a store (titip jual): awal | titip | terjual | retur, signed pcs. */
class ConsignmentMove extends Model
{
    use HasBusinessLine;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

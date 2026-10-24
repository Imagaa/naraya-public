<?php

namespace App\Models;

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Observers\ComplaintObserver;
use Database\Factories\ComplaintFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(ComplaintObserver::class)]
class Complaint extends Model
{
    /** @use HasFactory<ComplaintFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tipe' => ComplaintType::class,
            'status' => ComplaintStatus::class,
            'nominal_penyesuaian' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}

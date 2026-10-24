<?php

namespace App\Models;

use Database\Factories\SalesProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'area_id', 'phone', 'pending_phone', 'pending_phone_requested_at', 'photo_path', 'joined_at', 'monthly_target', 'is_active'])]
class SalesProfile extends Model
{
    /** @use HasFactory<SalesProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'is_active' => 'boolean',
            'pending_phone_requested_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('s3')->url($this->photo_path) : null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'label',
    'province_id', 'province_name',
    'city_id', 'city_name',
    'district_id', 'district_name',
    'village_id', 'village_name',
    'detail', 'postal_code', 'is_default',
    'lat', 'lng',
])]
class Address extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * "Jl. Contoh No. 1, Kel. X, Kec. Y, Kota Z, Provinsi W, 12345" — the
     * single string snapshot stored on an order at checkout time.
     */
    public function toFullAddress(): string
    {
        $parts = array_filter([
            $this->detail,
            $this->village_name,
            $this->district_name,
            $this->city_name,
            $this->province_name,
            $this->postal_code,
        ]);

        return implode(', ', $parts);
    }
}

<?php

namespace App\Support\Sales;

use App\Enums\BusinessLine;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\User;
use App\Support\Stock\CarriedStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a sales account (user, 2026-10-12): the login goes, the person leaves every list and their stores
 * wait for a new sales person — but the user row stays, so every nota, absensi, stock move and visit keeps
 * their name. Refused while they still hold cash or goods: those must come back first.
 */
final class SalesAccounts
{
    public static function delete(User $sales): void
    {
        abort_unless($sales->isSales() && ! $sales->account_deleted_at, 404);

        if (($cash = CashHeld::for($sales)) > 0) {
            throw ValidationException::withMessages(['delete' => 'Masih memegang cash Rp '.number_format($cash, 0, ',', '.').' — setor dulu.']);
        }
        foreach (BusinessLine::cases() as $line) {
            if (CarriedStock::for($sales, $line)->isNotEmpty() || Consignments::returnsHeld($sales, $line)->isNotEmpty()) {
                throw ValidationException::withMessages(['delete' => 'Masih membawa barang '.$line->label().' — kembalikan dulu ke gudang (absen pulang / retur).']);
            }
        }

        DB::transaction(function () use ($sales) {
            $areaId = $sales->salesProfile?->area_id;
            $sales->salesProfile?->forceFill(['is_active' => false, 'area_id' => null])->saveQuietly();
            BuyerProfile::where('sales_user_id', $sales->id)->update(['sales_user_id' => null, 'area_id' => null]); // stores wait for a new sales person
            if ($areaId) {
                Area::whereKey($areaId)->delete();
            }
            $sales->deviceTokens()->delete();
            $sales->pushSubscriptions()->delete();
            $sales->forceFill(['password' => null, 'email' => null, 'phone' => null, 'remember_token' => null, 'account_deleted_at' => now()])->save();
        });
    }
}

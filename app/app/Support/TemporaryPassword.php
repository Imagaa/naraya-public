<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Passwords (or, for a phone login, 6-digit PINs) that Admin or Sales hand to
 * a buyer. Shown to the issuer once; the buyer must replace it on first
 * login (EnsureBuyerReady gate 1), so the person who created the account
 * can't keep using it.
 */
final class TemporaryPassword
{
    // No 0/O, 1/l/I — it's read aloud and retyped from WhatsApp.
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    public static function generate(): string
    {
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $out;
    }

    public static function generatePin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Hash a fresh temporary password (or PIN, for a phone login) onto the buyer and return the plain one, to be shown exactly once. */
    public static function issueFor(User $buyer, User $issuer): string
    {
        $plain = $buyer->usesPin() ? self::generatePin() : self::generate();
        $buyer->forceFill(['password' => Hash::make($plain), 'must_change_password' => true, 'password_set_by' => $issuer->id])->save();

        return $plain;
    }
}

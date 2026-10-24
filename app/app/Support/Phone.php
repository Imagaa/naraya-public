<?php

namespace App\Support;

final class Phone
{
    /** Indonesian mobile number → "62…" digits (stored form, and what wa.me wants), or null when there are no digits. */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        return match (true) {
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            default => '62'.$digits,
        };
    }

    /**
     * `like` pattern that finds a stored number (62… for stores, 0… for sales)
     * however it was typed: 0812-…, +62 812…, 62812…, 812…. Null under 6 digits.
     */
    public static function searchPattern(string $term): ?string
    {
        $digits = preg_replace('/\D/', '', $term);

        return strlen($digits) >= 6 ? '%'.substr((string) self::normalize($digits), 2).'%' : null;
    }
}

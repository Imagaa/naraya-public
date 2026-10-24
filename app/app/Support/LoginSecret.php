<?php

namespace App\Support;

use App\Models\User;

/**
 * A store's own new password — or 6-digit PIN for a phone login — as validated
 * on the first-login gate and the account security page.
 */
final class LoginSecret
{
    public static function noun(User|bool $pin): string
    {
        return self::pin($pin) ? 'PIN' : 'kata sandi';
    }

    public static function rules(User|bool $pin): array
    {
        if (! self::pin($pin)) {
            return ['required', 'string', 'min:8', 'confirmed'];
        }

        return ['required', 'digits:6', 'confirmed', function ($attribute, $value, $fail) {
            // the PINs anyone tries first
            if (preg_match('/^(\d)\1{5}$/', $value) || in_array($value, ['123456', '654321', '012345', '123123'], true)) {
                $fail('PIN ini terlalu mudah ditebak. Pilih 6 angka lain.');
            }
        }];
    }

    /** @return array<string, string> messages for $field */
    public static function messages(User|bool $pin, string $field): array
    {
        return self::pin($pin)
            ? [$field.'.required' => 'PIN baru wajib diisi.', $field.'.digits' => 'PIN terdiri dari 6 angka.', $field.'.confirmed' => 'Ulangi PIN tidak cocok.']
            : [$field.'.required' => 'Kata sandi baru wajib diisi.', $field.'.min' => 'Kata sandi minimal 8 karakter.', $field.'.confirmed' => 'Ulangi kata sandi tidak cocok.'];
    }

    /** Callers pass the account, or — before it exists (sign-up) — whether it will be a PIN. */
    private static function pin(User|bool $pin): bool
    {
        return $pin instanceof User ? $pin->usesPin() : $pin;
    }
}

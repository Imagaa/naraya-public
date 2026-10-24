<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The one credential check behind both login doors (buyer and staff).
 * Every way to fail — unknown email/phone, wrong password/PIN, wrong door,
 * deactivated account, a store with no login yet — leaves through the same
 * message, the same limiter hit and (via the dummy hash) the same amount of
 * work, so neither door reveals what kind of account an identifier belongs
 * to, or that it exists. No session exists until every check has passed.
 */
final class DoorAuthenticator
{
    /** bcrypt at the production cost (12), checked when the account is unknown so that path costs the same. */
    private const DUMMY_HASH = '$2y$12$GiypT1KnyCui.tEjnWAXo.EYG3G5oLnYKMroJGid2vIM/ZwChznFW';

    public const FAILED = 'Email atau kata sandi salah.';

    public const FAILED_PIN = 'Nomor HP atau PIN salah.';

    /**
     * $login is ['email' => …] or, for a store's phone login, ['phone' => '62…'] with a 6-digit PIN as $password.
     * A phone login is also limited per number across all IPs: a PIN is only a million options.
     *
     * @param  array{email?: string, phone?: string}  $login
     * @param  array<int, Role>  $roles
     */
    public static function attempt(array $login, string $password, bool $remember, array $roles, string $throttleKey): User
    {
        $field = isset($login['phone']) ? 'phone' : 'email';
        $keys = $field === 'phone' ? [$throttleKey, 'pin|'.$login['phone']] : [$throttleKey];
        $limits = [$throttleKey => [5, 60], 'pin|'.($login['phone'] ?? '') => [10, 900]];

        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, $limits[$key][0])) {
                throw ValidationException::withMessages([
                    $field => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
                ]);
            }
        }

        $user = User::where($field, $login[$field])->first();

        if ($user?->hasAccount()) {
            $valid = Hash::check($password, $user->password);
        } else {
            Hash::check($password, self::DUMMY_HASH);
            $valid = false;
        }

        if (! $valid || ! in_array($user->role, $roles, true) || ! self::isActive($user)) {
            foreach ($keys as $key) {
                RateLimiter::hit($key, $limits[$key][1]);
            }

            throw ValidationException::withMessages([$field => $field === 'phone' ? self::FAILED_PIN : self::FAILED]);
        }

        foreach ($keys as $key) {
            RateLimiter::clear($key);
        }
        Auth::login($user, $remember);
        session()->regenerate();

        return $user;
    }

    private static function isActive(User $user): bool
    {
        return match ($user->role) {
            Role::User => (bool) ($user->buyerProfile?->is_active ?? true),
            Role::Sales => (bool) ($user->salesProfile?->is_active ?? true),
            default => true,
        };
    }
}

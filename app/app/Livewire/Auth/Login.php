<?php

namespace App\Livewire\Auth;

use App\Enums\Role;
use App\Support\DoorAuthenticator;
use App\Support\Phone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * The marketplace door: buyers only, by email + password or phone + 6-digit PIN
 *. Staff use StaffLogin on the staff domain.
 */
class Login extends Component
{
    /** email | phone */
    public string $method = 'phone';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $pin = '';
    public bool $remember = true;

    /**
     * Keyed by identifier+IP (not IP alone) — this is what stops a brute-force
     * attempt against one account without also locking out everyone else
     * sharing that IP (office/warung NAT, mobile carrier CGNAT, etc).
     */
    protected function throttleKey(): string
    {
        $id = $this->method === 'phone' ? (string) Phone::normalize($this->phone) : Str::lower($this->email);

        return Str::transliterate($id.'|'.request()->ip());
    }

    public function authenticate()
    {
        if ($this->method === 'phone') {
            $this->validate(['phone' => 'required|string|max:30', 'pin' => 'required|digits:6'], [
                'phone.required' => 'Isi nomor HP.', 'pin.required' => 'Isi PIN 6 angka.', 'pin.digits' => 'PIN terdiri dari 6 angka.',
            ]);
        } else {
            $this->validate(['email' => 'required|email', 'password' => 'required']);
        }

        // Defense in depth: the `guest` middleware on the /login route already
        // blocks this for a fresh request, but a stale tab left open from
        // before a login elsewhere in the same browser can still submit here.
        if (Auth::check()) {
            return redirect(request()->getScheme().'://'.Auth::user()->role->portalDomain().'/');
        }

        $this->method === 'phone'
            ? DoorAuthenticator::attempt(['phone' => (string) Phone::normalize($this->phone)], $this->pin, $this->remember, [Role::User], $this->throttleKey())
            : DoorAuthenticator::attempt(['email' => $this->email], $this->password, $this->remember, [Role::User], $this->throttleKey());

        // Match the current request's scheme rather than hardcoding http —
        // production serves everything over https, and a secure session
        // cookie won't be sent back on a plain http redirect.
        return redirect(request()->getScheme().'://'.config('app.domain').'/');
    }

    public function render()
    {
        return view('livewire.auth.login')->layout('layouts.marketplace')->title('Masuk — Naraya');
    }
}

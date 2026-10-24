<?php

namespace App\Livewire\Marketplace\Onboarding;

use App\Http\Middleware\EnsureBuyerReady;
use App\Notifications\BuyerNotice;
use App\Support\LoginSecret;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;

/** Gate 1: a buyer whose password (or PIN, for a phone login) was issued by Admin/Sales replaces it before anything else. */
class NewPassword extends Component
{
    public string $password = '';
    public string $password_confirmation = '';

    public function save()
    {
        $user = auth()->user();
        abort_unless($user->must_change_password, 403);

        $this->validate(['password' => LoginSecret::rules($user)], LoginSecret::messages($user, 'password'));

        if (Hash::check($this->password, $user->password)) {
            $this->addError('password', ucfirst(LoginSecret::noun($user)).' baru harus berbeda dari '.LoginSecret::noun($user).' sementara.');

            return;
        }

        $user->forceFill([
            'password' => Hash::make($this->password),
            'must_change_password' => false,
            'password_set_by' => null,
            'remember_token' => Str::random(60),
        ])->save();

        // Refreshes this session's stored password hash, so AuthenticateSession
        // logs out every other session still carrying the old one.
        Auth::logoutOtherDevices($this->password);
        $noun = LoginSecret::noun($user);
        $user->notify(new BuyerNotice(ucfirst($noun).' berhasil diganti', 'Akun toko Anda sekarang hanya bisa dibuka dengan '.$noun.' baru Anda.', '/akun/keamanan', 'shield-check', 'success'));

        $next = EnsureBuyerReady::gateFor($user->fresh());

        return redirect($next ? route($next) : '/');
    }

    public function render()
    {
        $setter = auth()->user()->passwordSetBy;

        return view('livewire.marketplace.onboarding.new-password', [
            'setterName' => $setter?->name ?? 'staf Naraya',
            'setterRole' => $setter?->isSales() ? 'Sales' : 'Admin',
            'pin' => auth()->user()->usesPin(),
        ])->layout('layouts.marketplace')->title((auth()->user()->usesPin() ? 'Buat PIN baru' : 'Buat kata sandi baru').' — Naraya');
    }
}

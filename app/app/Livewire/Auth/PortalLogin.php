<?php

namespace App\Livewire\Auth;

use App\Enums\Role;
use App\Support\DoorAuthenticator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/** A staff portal's own door (admin or sales), on that portal's host; only its role gets in. */
class PortalLogin extends Component
{
    public Role $portal;

    public string $email = '';

    public string $password = '';

    public bool $remember = true;

    public function mount(?Role $portal = null): void
    {
        $this->portal = $portal ?? Role::staffPortalFor(request()->getHost()) ?? abort(404);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate($this->portal->value.'|'.Str::lower($this->email).'|'.request()->ip());
    }

    public function authenticate()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // This portal's guard, whatever host the Livewire request came through.
        Auth::shouldUse($this->portal->value);
        DoorAuthenticator::attempt(['email' => $this->email], $this->password, $this->remember, [$this->portal], $this->throttleKey());

        return redirect(request()->getScheme().'://'.$this->portal->portalDomain().'/');
    }

    public function render()
    {
        return view('livewire.auth.portal-login')->layout('layouts.staff-auth')->title('Masuk '.$this->portal->label().' — Naraya');
    }
}

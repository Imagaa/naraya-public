<?php

namespace App\Livewire\Admin\Concerns;

/**
 * Shared by every admin Livewire component for ->layout('layouts.admin')
 * consistency. Logout lives at App\Http\Controllers\Auth\LogoutController
 * (a real route, not a component action) — a wire:click in the layout
 * file sits outside any component's own DOM boundary, so Livewire never
 * binds it there.
 */
trait HasAdminShell
{
    //
}

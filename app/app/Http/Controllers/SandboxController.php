<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

/** The admin's "Reset data coba" button on the sandbox (App\Console\Commands\ResetSandbox). */
class SandboxController
{
    public function reset(): RedirectResponse
    {
        abort_unless(config('naraya.sandbox'), 404);
        set_time_limit(300);
        Artisan::call('sandbox:reset');

        return redirect('/login')->with('status', 'Data coba sudah direset. Masuk lagi dengan akun coba.');
    }
}

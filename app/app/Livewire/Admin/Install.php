<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HasAdminShell;
use Livewire\Component;

/**
 * Pasang aplikasi admin: the steps for the device that opens the page.
 * The server picks the block from the User-Agent; the page itself switches to "already installed" in standalone mode.
 */
class Install extends Component
{
    use HasAdminShell;

    /** android | ios-safari | ios-other | desktop */
    public string $device = 'desktop';

    public function mount(): void
    {
        $this->device = self::deviceFor((string) request()->userAgent());
    }

    public static function deviceFor(string $ua): string
    {
        $ios = (bool) preg_match('/iPhone|iPad|iPod/i', $ua);

        return match (true) {
            // on iPhone only Safari can add a web app to the home screen (Chrome, Firefox, Edge, in-app browsers can't)
            $ios && (bool) preg_match('/CriOS|FxiOS|EdgiOS|OPiOS|FBAN|FBAV|Instagram|Line\/|WhatsApp|GSA\//i', $ua) => 'ios-other',
            $ios => 'ios-safari',
            (bool) preg_match('/Android/i', $ua) => 'android',
            default => 'desktop',
        };
    }

    public function render()
    {
        return view('livewire.admin.install', [
            'address' => preg_replace('#^https?://#', '', route('admin.install')),
        ])->layout('layouts.admin')->title('Pasang aplikasi — Naraya Admin');
    }
}

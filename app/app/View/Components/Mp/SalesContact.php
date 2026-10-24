<?php

namespace App\View\Components\Mp;

use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class SalesContact extends Component
{
    public ?string $name = null;
    public ?string $phone = null;
    public ?string $wa = null;
    public ?string $area = null;

    public ?string $photo = null;

    public function __construct()
    {
        $profile = auth()->user()?->buyerProfile;
        $sales = $profile?->salesUser;

        $this->name = $sales?->name;
        $this->photo = $sales?->salesProfile?->photoUrl();
        $this->phone = $sales?->salesProfile?->phone;
        $this->wa = self::waNumber($this->phone);
        $this->area = $profile?->area?->name;
    }

    /** Indonesian mobile number → wa.me digits ("62…"), or null when there are no digits. */
    public static function waNumber(?string $phone): ?string
    {
        return Phone::normalize($phone);
    }

    public function shouldRender(): bool
    {
        return $this->name !== null;
    }

    public function render(): View
    {
        return view('components.mp.sales-contact');
    }
}

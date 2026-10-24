<?php

namespace App\Livewire\Marketplace\Onboarding;

use App\Http\Middleware\EnsureBuyerReady;
use App\Livewire\Concerns\CapturesStorefront;
use App\Models\Area;
use Livewire\Component;

/** Gate 2: buyers from before the storefront rule add their photo and store pin before shopping. */
class CompleteStore extends Component
{
    use CapturesStorefront;

    public function mount(): void
    {
        $profile = auth()->user()->buyerProfile;
        $this->lat = $profile?->latitude !== null ? (float) $profile->latitude : null;
        $this->lng = $profile?->longitude !== null ? (float) $profile->longitude : null;
    }

    public function save()
    {
        $user = auth()->user();
        $profile = $user->buyerProfile;

        $this->validate($this->storefrontRules(photoRequired: $profile->store_photo_path === null), $this->storefrontMessages());

        $profile->update([
            'store_photo_path' => $this->storeStorefrontPhoto() ?? $profile->store_photo_path,
            'latitude' => $this->lat,
            'longitude' => $this->lng,
            'area_id' => $profile->area_id ?? Area::nearestTo($this->lat, $this->lng)?->id,
        ]);

        // The default address without a pin gets this one, so deliveries can use it too.
        $user->addresses()->where('is_default', true)->whereNull('lat')->update(['lat' => $this->lat, 'lng' => $this->lng]);

        $next = EnsureBuyerReady::gateFor($user->fresh());

        return redirect($next ? route($next) : '/');
    }

    public function render()
    {
        return view('livewire.marketplace.onboarding.complete-store', [
            'currentPhotoUrl' => auth()->user()->buyerProfile?->storePhotoUrl(),
        ])->layout('layouts.marketplace', ['maps' => true])->title('Lengkapi data toko — Naraya');
    }
}

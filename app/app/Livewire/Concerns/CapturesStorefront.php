<?php

namespace App\Livewire\Concerns;

use Livewire\WithFileUploads;

/**
 * The storefront photo and store pin every buyer account must have —
 * shared by the Admin and Sales buyer forms and the buyer's own
 * "Lengkapi data toko" page.
 */
trait CapturesStorefront
{
    use WithFileUploads;

    public $storePhoto = null;

    public ?float $lat = null;

    public ?float $lng = null;

    /** The photo is only required when the profile doesn't have one yet; the pin always is. */
    protected function storefrontRules(bool $photoRequired): array
    {
        return [
            'storePhoto' => [$photoRequired ? 'required' : 'nullable', 'image', 'max:5120'],
            // Indonesia's bounding box — rejects a pin dropped on the wrong continent.
            'lat' => ['required', 'numeric', 'between:-11,6'],
            'lng' => ['required', 'numeric', 'between:94,142'],
        ];
    }

    protected function storefrontMessages(): array
    {
        return [
            'storePhoto.required' => 'Foto depan warung wajib diunggah.',
            'storePhoto.image' => 'File harus berupa foto.',
            'storePhoto.max' => 'Ukuran foto maksimal 5 MB.',
            'lat.required' => 'Tandai titik lokasi toko di peta.',
            'lat.between' => 'Titik lokasi harus berada di Indonesia.',
            'lng.required' => 'Tandai titik lokasi toko di peta.',
            'lng.between' => 'Titik lokasi harus berada di Indonesia.',
        ];
    }

    /** Stores a new upload and returns its path, or null when none was given (editing keeps the old photo). */
    protected function storeStorefrontPhoto(): ?string
    {
        return $this->storePhoto ? $this->storePhoto->store('stores', 's3') : null;
    }
}

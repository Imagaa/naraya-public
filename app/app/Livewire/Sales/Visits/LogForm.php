<?php

namespace App\Livewire\Sales\Visits;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/** The "Catat kunjungan" form, shared by the Visits page and the store detail page. */
class LogForm extends Component
{
    use WithFileUploads;

    public ?int $buyerId = null;

    /** Store detail page: the store is given, so there is no picker. */
    public bool $fixed = false;

    public string $notes = '';

    public $photo;

    /** Bumped on every save; keys the photo picker so its Alpine preview starts empty again. */
    public int $saved = 0;

    public function logVisit(): void
    {
        $this->validate([
            'buyerId' => ['required', Rule::exists('buyer_profiles', 'user_id')->where('sales_user_id', auth()->id())],
            'notes' => 'nullable|string|max:500',
            'photo' => 'nullable|image|max:5120',
        ], [
            'buyerId.required' => 'Pilih toko yang dikunjungi.',
            'buyerId.exists' => 'Toko ini tidak ditugaskan ke Anda.',
        ]);

        Visit::create([
            'sales_id' => auth()->id(),
            'buyer_id' => $this->buyerId,
            'notes' => $this->notes ?: null,
            'photo_path' => $this->photo?->store('visits', 's3'),
            'visited_at' => now(),
        ]);

        $this->reset($this->fixed ? ['notes', 'photo'] : ['buyerId', 'notes', 'photo']);
        $this->saved++;
        $this->dispatch('visit-logged');
    }

    public function render()
    {
        return view('livewire.sales.visits.log-form', [
            'buyers' => $this->fixed ? collect() : User::assignedTo(auth()->id())->orderBy('name')->get(),
        ]);
    }
}

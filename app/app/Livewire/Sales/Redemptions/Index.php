<?php

namespace App\Livewire\Sales\Redemptions;

use App\Enums\RedemptionStatus;
use App\Models\Redemption;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    /** @var array<int, mixed> redemption_id => uploaded photo */
    public array $proofPhotos = [];

    public function markDelivered(int $redemptionId): void
    {
        $redemption = Redemption::where('sales_id', auth()->id())->findOrFail($redemptionId);

        abort_unless($redemption->status === RedemptionStatus::Disetujui, 403);

        $this->validate([
            "proofPhotos.{$redemptionId}" => 'required|image|max:5120',
        ], [
            "proofPhotos.{$redemptionId}.required" => 'Foto bukti serah terima wajib diunggah.',
        ]);

        $path = $this->proofPhotos[$redemptionId]->store('redemptions', 's3');

        $redemption->update([
            'status' => RedemptionStatus::Diserahkan,
            'bukti_path' => $path,
            'diserahkan_oleh' => auth()->id(),
            'diserahkan_at' => now(),
        ]);

        unset($this->proofPhotos[$redemptionId]);
    }

    public function render()
    {
        return view('livewire.sales.redemptions.index', [
            'redemptions' => Redemption::where('sales_id', auth()->id())
                ->where('status', RedemptionStatus::Disetujui)
                ->with('user.buyerProfile', 'reward')
                ->latest()
                ->get(),
        ])->layout('layouts.sales');
    }
}

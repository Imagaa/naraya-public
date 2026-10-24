<?php

namespace App\Livewire\Admin\Redemptions;

use App\Enums\RedemptionStatus;
use App\Models\Redemption;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Show extends Component
{
    use WithFileUploads, HasAdminShell;

    public Redemption $redemption;

    public string $alasanPenolakan = '';

    public $buktiFoto;

    public function mount(Redemption $redemption): void
    {
        $this->redemption = $redemption->load('user.buyerProfile', 'reward');
    }

    public function approve()
    {
        abort_unless($this->redemption->status === RedemptionStatus::Diajukan, 403);

        $buyerProfile = $this->redemption->user->buyerProfile;
        $reward = $this->redemption->reward;

        if (! $buyerProfile || $buyerProfile->loyalty_points < $this->redemption->point_cost) {
            $this->addError('approve', 'Saldo point User sudah tidak mencukupi.');

            return;
        }

        if ($reward->stock < 1) {
            $this->addError('approve', 'Stok hadiah sudah habis.');

            return;
        }

        $buyerProfile->decrement('loyalty_points', $this->redemption->point_cost);
        $reward->decrement('stock');

        $this->redemption->update([
            'status' => RedemptionStatus::Disetujui,
            'diproses_oleh' => auth()->id(),
            // Whichever sales already covers this buyer hand-delivers the
            // reward — same person who already visits/delivers to them,
            // rather than assigning it separately.
            'sales_id' => $buyerProfile->sales_user_id,
        ]);

        $this->redemption->refresh();
    }

    public function reject()
    {
        abort_unless($this->redemption->status === RedemptionStatus::Diajukan, 403);

        $this->validate(['alasanPenolakan' => 'required|string|max:500']);

        $this->redemption->update([
            'status' => RedemptionStatus::Ditolak,
            'alasan_penolakan' => $this->alasanPenolakan,
            'diproses_oleh' => auth()->id(),
        ]);

        $this->redemption->refresh();
    }

    public function cancel()
    {
        abort_unless($this->redemption->status === RedemptionStatus::Disetujui, 403);

        $this->validate(['alasanPenolakan' => 'required|string|max:500']);

        $this->redemption->user->buyerProfile->increment('loyalty_points', $this->redemption->point_cost);
        $this->redemption->reward->increment('stock');

        $this->redemption->update([
            'status' => RedemptionStatus::Dibatalkan,
            'alasan_penolakan' => $this->alasanPenolakan,
            'diproses_oleh' => auth()->id(),
        ]);

        $this->redemption->refresh();
    }

    public function markDelivered()
    {
        abort_unless($this->redemption->status === RedemptionStatus::Disetujui, 403);

        $this->validate(['buktiFoto' => 'required|image|max:5120']);

        $path = $this->buktiFoto->store('redemptions', 's3');

        $this->redemption->update([
            'status' => RedemptionStatus::Diserahkan,
            'bukti_path' => $path,
            'diserahkan_oleh' => auth()->id(),
            'diserahkan_at' => now(),
        ]);

        $this->redemption->refresh();
    }

    public function render()
    {
        return view('livewire.admin.redemptions.show')->layout('layouts.admin');
    }
}

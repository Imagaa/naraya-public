<?php

namespace App\Livewire\Admin\Complaints;

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Models\Complaint;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Show extends Component
{
    use HasAdminShell;
    public Complaint $complaint;

    public string $resolusi = '';

    public ?int $nominalPenyesuaian = null;

    public string $catatanAdmin = '';

    public function mount(Complaint $complaint): void
    {
        $this->complaint = $complaint->load('order.buyer');
    }

    public function approve()
    {
        abort_unless($this->complaint->tipe === ComplaintType::KomplainPascaSelesai, 403);
        abort_unless($this->complaint->status === ComplaintStatus::Diajukan, 403);

        $rules = ['resolusi' => 'required|in:ganti_barang,refund,potongan_order_berikutnya'];
        if ($this->resolusi === 'potongan_order_berikutnya') {
            $rules['nominalPenyesuaian'] = 'required|integer|min:1';
        }
        $this->validate($rules);

        $this->complaint->update([
            'status' => ComplaintStatus::Diresolusi,
            'resolusi' => $this->resolusi,
            'nominal_penyesuaian' => $this->nominalPenyesuaian,
            'catatan_admin' => $this->catatanAdmin ?: null,
            'diproses_oleh' => auth()->id(),
        ]);

        if ($this->resolusi === 'potongan_order_berikutnya') {
            $this->complaint->order->buyer->buyerProfile->increment('credit_balance', $this->nominalPenyesuaian);
        }

        $this->complaint->refresh();
    }

    public function reject()
    {
        abort_unless($this->complaint->tipe === ComplaintType::KomplainPascaSelesai, 403);
        abort_unless($this->complaint->status === ComplaintStatus::Diajukan, 403);

        $this->validate(['catatanAdmin' => 'required|string|max:500']);

        $this->complaint->update([
            'status' => ComplaintStatus::Ditutup,
            'catatan_admin' => $this->catatanAdmin,
            'diproses_oleh' => auth()->id(),
        ]);

        $this->complaint->refresh();
    }

    public function render()
    {
        return view('livewire.admin.complaints.show')->layout('layouts.admin');
    }
}

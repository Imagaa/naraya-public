<?php

namespace App\Livewire\Admin\Notes;

use App\Enums\NotePaymentMethod;
use App\Enums\ProofStatus;
use App\Livewire\Admin\Concerns\FiltersByWorkQueue;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Livewire\Admin\Concerns\HasFilterPills;
use App\Livewire\Concerns\HasCrudList;
use App\Models\SalesNote;
use App\Support\ActiveLine;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/** Nota penjualan: every sales note of the active line. */
class Index extends Component
{
    use FiltersByWorkQueue, HasAdminShell, HasCrudList, HasFilterPills;

    public function render()
    {
        $line = ActiveLine::current();
        $filters = [
            'tunai' => fn (Builder $q) => $q->where('payment_method', NotePaymentMethod::Cash),
            'menunggu' => fn (Builder $q) => $q->where('proof_status', ProofStatus::Pending),
            'ditolak' => fn (Builder $q) => $q->where('proof_status', ProofStatus::Rejected),
        ];
        $base = fn () => SalesNote::forLine($line);

        $notes = $this->applyQueue($base(), 'admin.notes.index')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('buyer', fn ($b) => $b->where('name', 'like', "%{$this->search}%"))
                ->orWhereHas('salesUser', fn ($s) => $s->where('name', 'like', "%{$this->search}%"))))
            ->when(isset($filters[$this->filter]), fn ($q) => $filters[$this->filter]($q))
            ->with(['buyer:id,name', 'salesUser:id,name'])
            ->latest('id')
            ->paginate($this->perPage);

        $counts = ['all' => $base()->count()];
        foreach ($filters as $key => $apply) {
            $counts[$key] = $apply($base())->count();
        }

        return view('livewire.admin.notes.index', [
            'line' => $line,
            'notes' => $notes,
            'counts' => $counts,
            'queueLabel' => $this->queueLabel('admin.notes.index'),
        ])->layout('layouts.admin');
    }
}

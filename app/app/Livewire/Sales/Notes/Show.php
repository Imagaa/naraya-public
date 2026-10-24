<?php

namespace App\Livewire\Sales\Notes;

use App\Models\SalesNote;
use Livewire\Component;

/** The nota a sales person shows or shares with the store; print-friendly. */
class Show extends Component
{
    public SalesNote $note;

    public function mount(int $id): void
    {
        $note = SalesNote::findOrFail($id);
        abort_unless($note->sales_id === auth()->id(), 404);
        $this->note = $note->load(['items.product', 'buyer', 'salesUser']);
    }

    /** Plain text for WhatsApp / the share sheet. */
    public function shareText(): string
    {
        $n = $this->note;
        $rupiah = fn (int $v) => 'Rp '.number_format($v, 0, ',', '.');
        $lines = ["Nota {$n->number()} — Naraya".($n->line === \App\Enums\BusinessLine::Toys ? ' Toys' : ''), $n->buyer?->name ?? ''];
        foreach ($n->items as $i) {
            $lines[] = "{$i->product_name} {$i->qtyLabel()} @ ".$rupiah($i->price).' = '.$rupiah($i->subtotal);
        }
        $lines[] = 'Total '.$rupiah($n->total).' ('.$n->payment_method->label().')';
        $lines[] = 'Sales: '.$n->salesUser?->name.', '.$n->created_at->wib()->format('d/m/Y H:i');

        return implode("\n", $lines);
    }

    public function render()
    {
        return view('livewire.sales.notes.show', ['share' => $this->shareText()]);
    }
}

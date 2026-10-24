<?php

namespace App\Livewire\Sales\Notes;

use App\Models\SalesNote;
use App\Support\ActiveLine;
use Livewire\Component;

/** Nota saya: my sales notes in the active line, newest first. */
class Index extends Component
{
    public function render()
    {
        $line = ActiveLine::current();

        return view('livewire.sales.notes.index', [
            'line' => $line,
            'notes' => SalesNote::forLine($line)->where('sales_id', auth()->id())
                ->with('buyer:id,name')->latest('id')->limit(20)->get(),
        ]);
    }
}

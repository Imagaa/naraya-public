<?php

namespace App\Livewire\Sales;

use App\Enums\AttendanceType;
use App\Enums\BusinessLine;
use App\Models\Product;
use App\Support\AbsenCamera;
use App\Support\Sales\CashHeld;
use App\Support\Sales\Consignments;
use App\Support\Stock\CarriedStock;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The "Absen pulang" sheet: 1 setoran — every rupiah of cash
 * held is deposited first; 2 the goods going back (all carried stock, and store returns apart); 3 the photo.
 * The server checks the cash again and moves the goods with the attendance.
 */
class CheckOut extends Component
{
    /** setor | barang | foto */
    public string $step = 'setor';

    public function mount(): void
    {
        if (CashHeld::for(auth()->user()) === 0) {
            $this->step = 'barang';
        }
    }

    #[On('deposit-submitted')]
    public function deposited(): void {}

    public function next(): void
    {
        if ($this->step === 'setor' && CashHeld::for(auth()->user()) > 0) {
            $this->addError('cash', 'Setor dulu semua cash yang Anda bawa.');

            return;
        }
        $this->step = $this->step === 'setor' ? 'barang' : 'foto';
    }

    public function back(): void
    {
        $this->step = $this->step === 'foto' ? 'barang' : 'setor';
    }

    public function render()
    {
        $sales = auth()->user();
        $goods = collect(BusinessLine::cases())->mapWithKeys(fn ($line) => [$line->value => [
            'line' => $line,
            'carried' => CarriedStock::for($sales, $line),
            'returns' => Consignments::returnsHeld($sales, $line),
        ]])->filter(fn ($g) => $g['carried']->isNotEmpty() || $g['returns']->isNotEmpty());

        return view('livewire.sales.check-out', [
            'cash' => CashHeld::for($sales),
            'recap' => ($shift = \App\Support\Sales\Shift::open($sales)) ? \App\Support\Sales\Shift::recap($shift) : null,
            'goods' => $goods,
            'products' => Product::whereKey($goods->flatMap(fn ($g) => $g['carried']->keys()->merge($g['returns']->keys()))->unique()->all())
                ->get(['id', 'name', 'unit', 'pieces_per_unit'])->keyBy('id'),
            'cfg' => $this->step === 'foto' ? AbsenCamera::config($sales, AttendanceType::Pulang) : null,
        ]);
    }
}

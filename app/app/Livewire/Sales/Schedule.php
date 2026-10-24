<?php

namespace App\Livewire\Sales;

use App\Models\BuyerProfile;
use App\Support\Sales\StoreSchedules;
use Livewire\Component;

/**
 * Atur jadwal (sheet `jadwal`): the sales person's weekly store
 * schedule. Ticking happens on the phone (Alpine) across all seven days; one Simpan stores every changed day.
 */
class Schedule extends Component
{
    /** @param array<int|string, list<int|string>> $days weekday => buyer ids */
    public function save(StoreSchedules $schedules, array $days): void
    {
        $sales = auth()->user();
        foreach (StoreSchedules::DAYS as $d => $_) {
            $schedules->saveDay($sales, $d, $days[$d] ?? []);
        }
        $this->dispatch('sheet-done', message: 'Jadwal toko disimpan.');
    }

    public function render()
    {
        $sales = auth()->user();
        $byStore = StoreSchedules::daysByStore($sales);
        $days = array_fill_keys(array_keys(StoreSchedules::DAYS), []);
        foreach ($byStore as $id => $weekdays) {
            foreach ($weekdays as $d) {
                $days[$d][] = $id;
            }
        }

        return view('livewire.sales.schedule', [
            'days' => $days,
            'today' => StoreSchedules::today(),
            'stores' => BuyerProfile::where('sales_user_id', $sales->id)->with('user:id,name')->get(['user_id', 'address'])
                ->filter(fn ($p) => $p->user)->sortBy(fn ($p) => mb_strtolower($p->user->name))
                ->map(fn ($p) => ['id' => $p->user_id, 'name' => $p->user->name, 'sub' => $p->address ?: 'Alamat belum diisi'])->values(),
        ]);
    }
}

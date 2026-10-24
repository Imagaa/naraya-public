<?php

namespace App\Livewire\Sales\Attendances;

use App\Models\Attendance;
use App\Models\SalesDayOff;
use App\Support\BusinessDay;
use App\Support\DayOffs;
use Livewire\Component;

class Index extends Component
{
    public string $dayOffDate = '';

    public string $dayOffNote = '';

    /** Weekly day off: one per Monday–Sunday week, asked for from the day before. */
    public function requestDayOff(DayOffs $dayOffs): void
    {
        $this->resetErrorBag();
        $this->validate(['dayOffDate' => 'required|date_format:Y-m-d', 'dayOffNote' => 'nullable|string|max:300'], ['dayOffDate.required' => 'Pilih tanggal libur.']);
        $dayOffs->request(auth()->user(), $this->dayOffDate, $this->dayOffNote);
        $this->reset('dayOffDate', 'dayOffNote');
    }

    public function cancelDayOff(DayOffs $dayOffs, int $id): void
    {
        $this->resetErrorBag();
        $dayOffs->cancel(SalesDayOff::where('sales_id', auth()->id())->findOrFail($id), auth()->user());
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.sales.attendances.index', [
            'attendances' => Attendance::where('sales_id', $user->id)->orderByDesc('id')->limit(30)->get(),
            'weekOff' => app(DayOffs::class)->inWeek($user, BusinessDay::today()),
            'dayOffs' => SalesDayOff::where('sales_id', $user->id)->latest('date')->limit(8)->get(),
            'dayOffMin' => BusinessDay::today()->addDay()->toDateString(),
            'dayOffMax' => BusinessDay::today()->addDays(DayOffs::MAX_DAYS_AHEAD)->toDateString(),
        ]);
    }
}

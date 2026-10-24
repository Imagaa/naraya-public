<?php

namespace App\Livewire\Admin\Tracking;

use App\Enums\AttendanceType;
use App\Enums\StockTransferKind;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\Attendance;
use App\Models\BuyerProfile;
use App\Models\ConsignmentMove;
use App\Models\Product;
use App\Models\SalesNoteItem;
use App\Models\SalesTrackPoint;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Support\BusinessDay;
use App\Support\Sales\Shift;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Perjalanan sales — live (admin view; Beranda's "Sales sedang bekerja"):
 * everyone who checked in today, still working or done — since when, for how long, km, stores, the goods they took
 * from the warehouse and the goods that went out to stores — and one map with all their routes, each in its own
 * colour. The list re-renders every 15 s; the map (wire:ignore) follows by event (tick → tracking-update).
 */
class Index extends Component
{
    use HasAdminShell;

    /** One colour per sales person (same palette as Area sales). */
    public const COLORS = \App\Livewire\Admin\Areas\Index::COLORS;

    /** The sales person the map zooms to; null = everyone. */
    #[Url(as: 'sales')]
    public ?int $salesId = null;

    public function pick(?int $salesId): void
    {
        $this->salesId = $this->salesId === $salesId ? null : $salesId; // a second tap shows everyone again
        $this->tick();
    }

    public function tick(): void
    {
        $this->dispatch('tracking-update', data: $this->mapData($this->shifts()));
    }

    private function shifts()
    {
        return Attendance::where('tipe', AttendanceType::Masuk)->whereBetween('created_at', BusinessDay::bounds())
            ->with('salesUser:id,name')->orderBy('created_at')->get()
            ->filter(fn ($m) => $m->salesUser)->values()
            ->map(function (Attendance $m, int $i) {
                $recap = Shift::recap($m);

                return [
                    'masuk' => $m,
                    'color' => self::COLORS[$i % count(self::COLORS)],
                    'recap' => $recap,
                    'last' => SalesTrackPoint::where('attendance_id', $m->id)->latest('recorded_at')->first(['latitude', 'longitude', 'recorded_at']),
                    ...self::goods($m->sales_id, $recap['start'], $recap['end']),
                ];
            });
    }

    /**
     * The goods of the shift, as [Product, pcs] pairs: taken from the warehouse, and out to stores (left as titip
     * plus sold from what was carried).
     */
    private static function goods(int $salesId, $from, $to): array
    {
        $taken = StockTransferItem::whereHas('transfer', fn ($q) => $q->where('sales_id', $salesId)->where('kind', StockTransferKind::Ambil)->whereBetween('created_at', [$from, $to]))
            ->selectRaw('product_id, SUM(quantity) as q')->groupBy('product_id')->pluck('q', 'product_id');
        $out = ConsignmentMove::where('sales_id', $salesId)->where('kind', 'titip')->whereBetween('created_at', [$from, $to])
            ->selectRaw('product_id, SUM(quantity) as q')->groupBy('product_id')->pluck('q', 'product_id');
        SalesNoteItem::whereHas('note', fn ($q) => $q->where('sales_id', $salesId)->where('source', 'bawaan')->whereBetween('created_at', [$from, $to]))
            ->selectRaw('product_id, SUM(quantity) as q')->groupBy('product_id')->pluck('q', 'product_id')
            ->each(function ($q, $id) use (&$out) { $out[$id] = ($out[$id] ?? 0) + $q; });
        $products = Product::whereKey($taken->keys()->merge($out->keys()))->get(['id', 'name', 'unit', 'pieces_per_unit'])->keyBy('id');
        $pairs = fn ($c) => $c->map(fn ($q, $id) => [$products[$id] ?? null, (int) $q])->filter(fn ($p) => $p[1] > 0)->values();

        return ['taken' => $pairs($taken), 'out' => $pairs($out)];
    }

    /** Every shift's route, start, stores and last position, for the one map. */
    private function mapData($shifts): array
    {
        $visited = $shifts->flatMap(fn ($s) => collect($s['recap']['stores'])->pluck('id'))->unique();
        $pins = BuyerProfile::whereIn('user_id', $visited)->whereNotNull('latitude')->get(['user_id', 'latitude', 'longitude'])->keyBy('user_id');

        return [
            'focus' => $this->salesId,
            'shifts' => $shifts->map(fn ($s) => [
                'id' => $s['masuk']->sales_id,
                'name' => $s['masuk']->salesUser->name,
                'color' => $s['color'],
                'open' => $s['recap']['open'],
                'path' => SalesTrackPoint::where('attendance_id', $s['masuk']->id)->orderBy('recorded_at')->get(['latitude', 'longitude'])
                    ->map(fn ($p) => [(float) $p->latitude, (float) $p->longitude])->values(),
                'start' => $s['masuk']->latitude !== null ? [(float) $s['masuk']->latitude, (float) $s['masuk']->longitude] : null,
                'last' => $s['last'] ? ['at' => [(float) $s['last']->latitude, (float) $s['last']->longitude], 'time' => $s['last']->recorded_at->wib()->format('H.i')] : null,
                'stores' => collect($s['recap']['stores'])->filter(fn ($st) => isset($pins[$st['id']]))->map(fn ($st) => [
                    'at' => [(float) $pins[$st['id']]->latitude, (float) $pins[$st['id']]->longitude], 'name' => $st['name'], 'time' => $st['at']->wib()->format('H.i'), 'closed' => $st['closed'],
                ])->values(),
            ])->values(),
        ];
    }

    public function render()
    {
        $shifts = $this->shifts();

        return view('livewire.admin.tracking.index', [
            'shifts' => $shifts,
            'working' => $shifts->filter(fn ($s) => $s['recap']['open'])->count(),
            'activeSales' => User::activeSales()->count(),
            'map' => $this->mapData($shifts),
        ])->layout('layouts.admin');
    }
}

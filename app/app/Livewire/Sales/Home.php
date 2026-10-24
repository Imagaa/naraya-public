<?php

namespace App\Livewire\Sales;

use App\Enums\AttendanceType;
use App\Livewire\Sales\Concerns\HostsSheets;
use App\Models\Attendance;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Support\ActiveLine;
use App\Support\BusinessDay;
use App\Support\Sales\DeliveryRoutes;
use App\Support\Sales\StoreSchedules;
use App\Support\SalesTarget;
use App\Support\Stock\CarriedStock;
use Livewire\Component;

/**
 * Kerja — the sales app's one working page: check-in/out,
 * the stock carried, today's stores, the cash to deposit. Every task opens as a sheet over it.
 */
class Home extends Component
{
    use HostsSheets;

    protected function sheets(): array
    {
        return ['masuk', 'pulang', 'ambil', 'retur', 'nota', 'nota-detail', 'toko', 'stok-awal', 'titip', 'toko-baru', 'setor', 'investasi', 'jadwal'];
    }

    /** Field work recorded offline just reached the server (resources/js/sales-offline.js): re-render. */
    #[\Livewire\Attributes\On('offline-synced')]
    public function synced(): void {}

    public function render()
    {
        $sales = auth()->user();
        $line = ActiveLine::current();
        $today = Attendance::where('sales_id', $sales->id)->whereBetween('created_at', BusinessDay::bounds())->get();
        $carried = CarriedStock::for($sales, $line);
        $prices = Product::whereKey($carried->keys())->get(['id', 'name', 'price', 'unit', 'pieces_per_unit'])->keyBy('id');
        $route = DeliveryRoutes::active($sales);
        $all = $this->allStores($sales);

        return view('livewire.sales.home', [
            'line' => $line,
            'masuk' => $today->firstWhere('tipe', AttendanceType::Masuk),
            'pulang' => $today->firstWhere('tipe', AttendanceType::Pulang),
            'carried' => $carried,
            'carriedProducts' => $prices,
            'carriedValue' => (int) $carried->sum(fn ($q, $id) => $prices[$id]?->valueOf($q) ?? 0),
            'route' => $route,
            'stores' => $route ? $this->routeStops($route) : $all->whereIn('id', StoreSchedules::storeIdsFor($sales, StoreSchedules::today()))->values(),
            'all' => $all,
            'weekday' => StoreSchedules::DAYS[StoreSchedules::today()],
            'cashHeld' => \App\Support\Sales\CashHeld::for($sales),
            'recap' => ($shift = \App\Support\Sales\Shift::today($sales)) ? \App\Support\Sales\Shift::recap($shift) : null,
            'target' => (int) ($sales->salesProfile?->monthly_target ?? 0),
            'realized' => SalesTarget::realized($sales->id),
        ])->layout('layouts.sales');
    }

    /** Today's stores once a route is started: its stops in order. */
    private function routeStops($route)
    {
        return $route->stops()->with('buyer:id,name', 'buyer.buyerProfile:id,user_id,owner_name,address,latitude,longitude,store_photo_path,consignment_opened_at')->orderBy('position')->get()
            ->filter(fn ($s) => $s->buyer)
            ->map(fn ($s) => self::row($s->buyer->id, $s->buyer->name, $s->buyer->buyerProfile, $s->status, $s->closed_note));
    }

    /**
     * Every store assigned to the sales person, by name, each with its schedule days (the search and "Semua toko"),
     * and the source of today's scheduled stores before a route is started.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, owner: ?string, sub: string, days: ?string, status: string, incomplete: bool, lat: ?float, lng: ?float}>
     */
    private function allStores($sales)
    {
        $days = StoreSchedules::daysByStore($sales);

        return BuyerProfile::where('sales_user_id', $sales->id)->with('user:id,name')->get(['id', 'user_id', 'owner_name', 'address', 'latitude', 'longitude', 'store_photo_path', 'consignment_opened_at'])
            ->filter(fn ($p) => $p->user)->sortBy(fn ($p) => mb_strtolower($p->user->name))
            ->map(fn ($p) => self::row($p->user_id, $p->user->name, $p, 'pending') + ['days' => StoreSchedules::label($days[$p->user_id] ?? [])])->values();
    }

    private static function row(int $id, string $name, ?BuyerProfile $p, string $status, ?string $closedNote = null): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'owner' => $p?->owner_name,
            'sub' => match ($status) { 'visited' => 'Sudah dibuat nota', 'closed' => 'Toko tutup'.($closedNote ? ' — '.$closedNote : ''), default => $p?->address ?: 'Alamat belum diisi' },
            'status' => $status,
            'incomplete' => $p !== null && ! $p->hasCompleteStoreData(),
            'opened' => $p?->consignment_opened_at !== null, // first count of the goods at the store done
            'lat' => $p?->latitude !== null ? (float) $p->latitude : null,
            'lng' => $p?->longitude !== null ? (float) $p->longitude : null,
        ];
    }
}

<?php

namespace App\Livewire\Sales\Routes;

use App\Livewire\Sales\Concerns\HostsSheets;
use App\Models\BuyerProfile;
use App\Models\DeliveryRoute;
use App\Models\Product;
use App\Models\SalesNote;
use App\Support\ActiveLine;
use App\Support\Sales\DeliveryRoutes;
use App\Support\Sales\StoreSchedules;
use App\Support\Stock\CarriedStock;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Rute tab (its own menu again since 2026-10-12): pick stores → set the order →
 * start; then work the active route stop by stop — each store opens as a sheet — and finish with a recap.
 */
class Index extends Component
{
    use HostsSheets, WithFileUploads;

    protected function sheets(): array
    {
        return ['toko', 'stok-awal', 'titip', 'nota', 'nota-detail', 'investasi'];
    }

    public string $step = 'pilih'; // pilih | atur

    /** @var list<int|string> */
    public array $selected = [];

    public string $search = '';

    /** @var list<int> buyer ids in stop order */
    public array $order = [];

    public ?int $closingStopId = null;

    public $closedPhoto = null;

    public string $closedNote = '';

    /** A new route starts from today's scheduled stores, already ticked. */
    public function mount(): void
    {
        $this->selected = StoreSchedules::storeIdsFor(auth()->user(), StoreSchedules::today());
    }

    public function selectAll(): void
    {
        $this->selected = $this->stores()->pluck('user_id')->all();
    }

    public function arrange(?float $lat = null, ?float $lng = null): void
    {
        $this->resetErrorBag();
        $chosen = $this->stores()->whereIn('user_id', array_map('intval', $this->selected));
        if ($chosen->isEmpty()) {
            $this->addError('route', 'Pilih minimal satu toko.');

            return;
        }
        [$located, $unlocated] = $chosen->partition(fn (BuyerProfile $p) => $p->latitude !== null && $p->longitude !== null);
        $points = $located->mapWithKeys(fn (BuyerProfile $p) => [$p->user_id => [(float) $p->latitude, (float) $p->longitude]])->all();

        $this->order = array_merge(DeliveryRoutes::nearestOrder($points, $lat, $lng), $unlocated->pluck('user_id')->all());
        $this->step = 'atur';
    }

    public function move(int $index, int $direction): void
    {
        $to = $index + $direction;
        if (isset($this->order[$index], $this->order[$to])) {
            [$this->order[$index], $this->order[$to]] = [$this->order[$to], $this->order[$index]];
        }
    }

    public function back(): void
    {
        $this->step = 'pilih';
    }

    public function start(DeliveryRoutes $routes): void
    {
        $routes->start(auth()->user(), $this->order);
        $this->reset('step', 'selected', 'order', 'search');
    }

    public function openClose(int $stopId): void
    {
        $this->resetErrorBag();
        $this->reset('closedPhoto', 'closedNote');
        $this->closingStopId = $stopId;
    }

    public function cancelClose(): void
    {
        $this->reset('closingStopId', 'closedPhoto', 'closedNote');
    }

    public function saveClose(DeliveryRoutes $routes): void
    {
        $this->resetErrorBag();
        $this->validate(['closedPhoto' => 'nullable|image|max:8192', 'closedNote' => 'nullable|string|max:500']);
        $stop = $this->route()?->stops()->whereKey($this->closingStopId)->where('status', 'pending')->firstOrFail();
        $routes->close($stop, $this->closedPhoto, $this->closedNote);
        $this->reset('closingStopId', 'closedPhoto', 'closedNote');
    }

    public function finish(DeliveryRoutes $routes): void
    {
        if ($route = $this->route()) {
            $routes->finish($route);
        }
    }

    private function route(): ?DeliveryRoute
    {
        return DeliveryRoutes::active(auth()->user());
    }

    private function stores()
    {
        return BuyerProfile::where('sales_user_id', auth()->id())->with('user:id,name')->get()->sortBy(fn ($p) => $p->user?->name)->values();
    }

    public function render()
    {
        $route = $this->route()?->load('stops.buyer.buyerProfile');

        return view('livewire.sales.routes.index', $route ? $this->activeData($route) : $this->planData())->layout('layouts.sales');
    }

    private function planData(): array
    {
        $stores = $this->stores();
        $byId = $stores->keyBy('user_id');
        $term = mb_strtolower(trim($this->search));
        // one number per store, the same in the list and on its map pin: list order while picking, stop order once arranged
        $numbers = ($this->step === 'atur' ? collect($this->order) : $stores->pluck('user_id'))->values()->flip()->map(fn ($i) => $i + 1);

        return [
            'route' => null,
            'stores' => $term === '' ? $stores : $stores->filter(fn ($p) => str_contains(mb_strtolower((string) $p->user?->name), $term)),
            'numbers' => $numbers,
            'ordered' => collect($this->order)->map(fn ($id) => $byId[$id] ?? null)->filter()->values(),
            'mapPoints' => ($this->step === 'atur' ? collect($this->order)->map(fn ($id) => $byId[$id] ?? null)->filter() : $stores)
                ->filter(fn ($p) => $p->latitude !== null && $p->longitude !== null)
                ->map(fn ($p) => ['id' => $p->user_id, 'no' => $numbers[$p->user_id] ?? null, 'name' => ($numbers[$p->user_id] ?? '?').'. '.$p->user?->name,
                    'lat' => (float) $p->latitude, 'lng' => (float) $p->longitude,
                    'on' => $this->step === 'atur' || in_array($p->user_id, array_map('intval', $this->selected), true)])
                ->values()->all(),
        ];
    }

    private function activeData(DeliveryRoute $route): array
    {
        $line = ActiveLine::current();
        $carried = CarriedStock::for(auth()->user(), $line);
        $products = Product::whereKey($carried->keys())->get()->keyBy('id');
        $notes = SalesNote::whereIn('route_stop_id', $route->stops->pluck('id'))->withSum('items', 'quantity')->get();
        $located = $route->stops->filter(fn ($s) => $s->buyer?->buyerProfile?->latitude !== null);
        $pendingLocated = $located->where('status', 'pending')->values();

        return [
            'route' => $route,
            'mapPoints' => $located->map(fn ($s) => ['id' => $s->id, 'no' => $s->position, 'name' => $s->position.'. '.$s->buyer->name,
                'lat' => (float) $s->buyer->buyerProfile->latitude, 'lng' => (float) $s->buyer->buyerProfile->longitude, 'on' => $s->status === 'pending'])->values()->all(),
            'counts' => $route->stops->countBy('status')->all() + ['pending' => 0, 'visited' => 0, 'closed' => 0],
            'notesByKind' => $notes->groupBy(fn (SalesNote $n) => $n->line->label().' · '.$n->payment_method->label())->map(fn ($g) => ['count' => $g->count(), 'total' => (int) $g->sum('total')])->sortKeys(),
            'itemsSold' => (int) $notes->sum('items_sum_quantity'),
            'carriedItems' => $carried->count(),
            'carriedValue' => (int) $carried->sum(fn ($q, $id) => $products[$id]?->valueOf($q) ?? 0),
            'line' => $line,
            'navigateAll' => DeliveryRoutes::mapsUrl($pendingLocated->take(10)->map(fn ($s) => self::point($s->buyer->buyerProfile))->all()),
        ];
    }

    /** A store as a {lat, lng} point for DeliveryRoutes::mapsUrl(). */
    public static function point(?BuyerProfile $p): array
    {
        return ['lat' => $p?->latitude, 'lng' => $p?->longitude];
    }
}

<?php

namespace App\Livewire\Admin\Areas;

use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\Area;
use App\Models\BuyerProfile;
use Livewire\Component;

/**
 * The sales people's areas, made from their stores' pins (App\Support\SalesAreas): look only. The map shows each
 * sales person's circle and stores in their own colour, so whose store is where reads at a glance.
 */
class Index extends Component
{
    use HasAdminShell;

    /** One colour per sales person, in the order of the list. */
    public const COLORS = ['#2563EB', '#DC2626', '#16A34A', '#9333EA', '#EA580C', '#0891B2', '#DB2777', '#65A30D', '#CA8A04', '#4F46E5'];

    public function render()
    {
        $areas = Area::orderBy('name')->withCount('buyerProfiles')->with(['salesProfiles' => fn ($q) => $q->with('user:id,name')])->get();
        $pins = BuyerProfile::whereNotNull('latitude')->whereNotNull('longitude')->with('user:id,name')->get(['user_id', 'sales_user_id', 'latitude', 'longitude']);
        $groups = $areas->values()->map(function (Area $area, int $i) use ($pins) {
            $sales = $area->salesProfiles->first();

            return [
                'name' => $sales?->user?->name ?? $area->name, 'area' => $area->name, 'color' => self::COLORS[$i % count(self::COLORS)],
                'lat' => $area->lat, 'lng' => $area->lng, 'radiusKm' => $area->radius_km,
                'stores' => $pins->where('sales_user_id', $sales?->user_id)->filter(fn ($p) => $sales)->map(fn ($p) => ['name' => $p->user?->name, 'lat' => (float) $p->latitude, 'lng' => (float) $p->longitude])->values(),
            ];
        });
        $orphans = $pins->whereNull('sales_user_id');
        if ($orphans->isNotEmpty()) {
            $groups->push(['name' => 'Belum ada sales', 'area' => null, 'color' => '#6B7280', 'lat' => null, 'lng' => null, 'radiusKm' => null,
                'stores' => $orphans->map(fn ($p) => ['name' => $p->user?->name, 'lat' => (float) $p->latitude, 'lng' => (float) $p->longitude])->values()]);
        }

        return view('livewire.admin.areas.index', ['areas' => $areas, 'groups' => $groups])->layout('layouts.admin');
    }
}

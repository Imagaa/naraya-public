<?php

namespace App\Http\Controllers;

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Models\BuyerProfile;
use App\Models\ConsignmentMove;
use App\Models\Product;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Sales\Consignments;
use App\Support\Sales\DeliveryRoutes;
use App\Support\Sales\SalesNotes;
use App\Support\Sales\StoreRegistration;
use App\Support\Stock\CarriedStock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Field work with no signal. data(): what the phone's offline forms
 * check against. sync(): one queued action, replayed through the same services as the online sheets, applied once
 * per phone-made uuid.
 */
class OfflineController
{
    public function data(Request $request): JsonResponse
    {
        $sales = $request->user();
        $line = ActiveLine::current();
        $carried = CarriedStock::for($sales, $line);
        $profiles = BuyerProfile::where('sales_user_id', $sales->id)->with('user:id,name')->get(['user_id', 'consignment_opened_at'])->filter(fn ($p) => $p->user);
        $balances = ConsignmentMove::whereIn('buyer_id', $profiles->pluck('user_id'))->forLine($line)
            ->groupBy('buyer_id', 'product_id')->selectRaw('buyer_id, product_id, SUM(quantity) as qty')->get()
            ->filter(fn ($m) => (int) $m->qty > 0)->groupBy('buyer_id');
        $stops = DeliveryRoutes::active($sales)?->stops()->where('status', 'pending')->pluck('id', 'buyer_id') ?? collect();
        $products = Product::forLine($line)
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $carried->keys()->merge($balances->flatten(1)->pluck('product_id'))))
            ->orderBy('name')->get(['id', 'name', 'price', 'unit', 'pieces_per_unit']);

        return response()->json([
            'line' => ['value' => $line->value, 'label' => $line->label()],
            'transferTo' => config('naraya.transfer_to'),
            'products' => $products->mapWithKeys(fn ($p) => [$p->id => ['name' => $p->name, 'price' => (int) $p->price, 'unit' => $p->unit ?: 'pcs', 'ppu' => $p->ppu()]]),
            'carried' => (object) $carried->all(),
            'stores' => $profiles->sortBy(fn ($p) => $p->user->name)->values()->map(fn ($p) => [
                'id' => $p->user_id,
                'name' => $p->user->name,
                'opened' => $p->consignment_opened_at !== null,
                'stop' => $stops[$p->user_id] ?? null,
                'last' => Consignments::lastVisitAt($p->user_id, $line)?->toIso8601String(),
                'at' => (object) ($balances[$p->user_id] ?? collect())->mapWithKeys(fn ($m) => [$m->product_id => (int) $m->qty])->all(),
            ]),
        ])->header('Cache-Control', 'no-store');
    }

    public function sync(Request $request, Consignments $consignments, SalesNotes $notes, DeliveryRoutes $routes): JsonResponse
    {
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'type' => 'required|in:awal,titip,nota,tutup,toko',
            'line' => ['required', Rule::enum(BusinessLine::class)],
            'recorded_at' => 'required|date',
            'payload' => 'required|json',
            'photo' => 'nullable|image|max:8192',
        ]);
        $sales = $request->user();
        if (DB::table('offline_actions')->where('uuid', $data['uuid'])->exists()) {
            return response()->json(['message' => 'Sudah terkirim.']);
        }
        $line = BusinessLine::from($data['line']);
        $p = json_decode($data['payload'], true);
        $photo = $request->file('photo');
        $store = (int) ($p['store'] ?? 0);
        $pcs = fn (string $key) => collect($p[$key] ?? [])->mapWithKeys(fn ($q, $id) => [(int) $id => (int) $q])->all();

        try {
            $message = DB::transaction(function () use ($data, $sales, $line, $p, $photo, $store, $pcs, $consignments, $notes, $routes) {
                if (! ActiveLine::isOpen($line)) {
                    throw ValidationException::withMessages(['line' => 'Lini '.$line->label().' sedang ditutup.']);
                }
                $method = NotePaymentMethod::tryFrom((string) ($p['method'] ?? 'cash')) ?? NotePaymentMethod::Cash;
                $proof = $method === NotePaymentMethod::Cash ? null : $photo;
                $name = fn () => User::find($store)?->name ?? 'toko';

                $message = match ($data['type']) {
                    'awal' => tap('Barang awal '.$name().' dicatat.', fn () => $consignments->open($sales, $store, $line, $pcs('items'))),
                    'titip' => tap('Kunjungan '.$name().' terkirim.', fn () => $consignments->visit($sales, $store, $line, $pcs('remaining'), $pcs('titip'), $method, $proof)),
                    'nota' => tap('Nota '.$name().' terkirim.', fn () => $notes->create($sales, $line, $store, $pcs('items'), $method, $proof,
                        DeliveryRoutes::active($sales)?->stops()->where('buyer_id', $store)->where('status', 'pending')->value('id'))),
                    'tutup' => tap($name().' dicatat tutup.', function () use ($sales, $store, $routes, $photo, $p) {
                        $stop = DeliveryRoutes::active($sales)?->stops()->where('buyer_id', $store)->where('status', 'pending')->first()
                            ?? throw ValidationException::withMessages(['store' => 'Toko ini sudah tidak ada di rute yang berjalan.']);
                        $routes->close($stop, $photo, $p['note'] ?? null);
                    }),
                    'toko' => $this->registerStore($sales, $p, $photo),
                };
                DB::table('offline_actions')->insert([
                    'uuid' => $data['uuid'], 'user_id' => $sales->id, 'type' => $data['type'],
                    'recorded_at' => \Illuminate\Support\Carbon::parse($data['recorded_at']), 'created_at' => now(),
                ]);

                return $message;
            });
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Sudah terkirim.']); // the same item, sent twice at once
        }

        return response()->json(['message' => $message]);
    }

    private function registerStore(User $sales, array $p, $photo): string
    {
        Validator::make($p + ['photo' => $photo], [
            'name' => 'required|string|max:255',
            'owner' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^(\+?62|0)8[0-9\- ]{7,14}$/'],
            'address' => 'required|string|max:500',
            'lat' => ['required', 'numeric', 'between:-11,6'],
            'lng' => ['required', 'numeric', 'between:94,142'],
            'photo' => ['required', 'image', 'max:8192'],
        ], [
            'phone.regex' => 'Nomor HP toko tidak valid.',
            'lat.*' => 'Titik lokasi toko tidak terbaca.',
            'lng.*' => 'Titik lokasi toko tidak terbaca.',
            'photo.required' => 'Foto depan warung wajib.',
        ])->validate();

        $store = StoreRegistration::create($sales, $p['name'], $p['owner'], $p['phone'], $p['address'], (float) $p['lat'], (float) $p['lng'],
            $photo->store('stores', 's3'));

        return 'Toko '.$store->name.' terdaftar.';
    }
}

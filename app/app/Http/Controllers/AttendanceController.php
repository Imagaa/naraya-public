<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Enums\BusinessLine;
use App\Models\AttendanceLocation;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\AttendanceCalendar;
use App\Support\AttendanceWatermark;
use App\Support\BusinessDay;
use App\Support\Geo;
use App\Support\Sales\CashHeld;
use App\Support\Sales\Consignments;
use App\Support\Stock\CarriedStock;
use App\Support\Stock\StockTransfers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Check-in/out, any time of day. Every decision is server-side; the client geofence chip is UX only.
 * Check-in also records the stock taken from the warehouse; check-out needs every rupiah deposited and gives
 * back all carried stock and the store returns — all or nothing. Routes are their own menu.
 */
class AttendanceController extends Controller
{
    private const TOKEN_TTL = 120;

    /** One-time token issued when the camera opens, so an old payload can't be replayed. */
    public function session(Request $request): JsonResponse
    {
        $token = Str::random(40);
        Cache::put("att-token:{$token}", $request->user()->id, self::TOKEN_TTL);

        return response()->json(['token' => $token, 'expires_at' => now()->addSeconds(self::TOKEN_TTL)->toIso8601String()]);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json((new AttendanceCalendar)->today($request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'tipe' => 'required|in:masuk,pulang',
            'token' => 'required|string|max:64',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'akurasi' => 'required|numeric|min:0',
            'foto' => 'required|file|image|max:5120',
            'catatan' => 'nullable|string|max:500',
            'idempotency_key' => 'required|string|max:64',
            'items' => 'nullable|json', // check-in: {"product_id": qty} taken from the warehouse
        ]);
        $user = $request->user();

        // Before the token check: a retry after a successful submit has a spent token but must still get its record back.
        $existing = Attendance::where('idempotency_key', $v['idempotency_key'])->where('sales_id', $user->id)->first();
        if ($existing) {
            return response()->json(['id' => $existing->id, 'status' => 'ok', 'hasil' => $existing->statusText()]);
        }

        // Two taps from two tabs must not both pass the "already checked in today" check below.
        $lock = Cache::lock("att-submit:{$user->id}", 15);
        if (! $lock->get()) {
            return $this->reject('Absen sebelumnya masih diproses, coba lagi sebentar.');
        }

        try {
            return $this->record($request, $v, $user);
        } finally {
            $lock->release();
        }
    }

    private function record(Request $request, array $v, User $user): JsonResponse
    {
        // (int): the redis store hands numbers back as strings.
        if ((int) Cache::pull("att-token:{$v['token']}") !== $user->id) {
            return $this->reject('Sesi absen kedaluwarsa, buka kamera lagi.');
        }

        $tipe = AttendanceType::from($v['tipe']);
        $today = Attendance::where('sales_id', $user->id)->whereBetween('created_at', BusinessDay::bounds())->get();
        if ($today->contains('tipe', $tipe)) {
            return $this->reject("Sudah absen {$tipe->value} hari ini.");
        }
        if ($tipe === AttendanceType::Pulang && ! $today->contains('tipe', AttendanceType::Masuk)) {
            return $this->reject('Absen masuk dulu.');
        }

        $calendar = new AttendanceCalendar;
        $lat = (float) $v['latitude'];
        $lng = (float) $v['longitude'];
        $akurasi = (float) $v['akurasi'];
        if ($akurasi > $calendar->setting()->max_akurasi_m) {
            return $this->reject('Sinyal GPS lemah (±'.round($akurasi).' m). Coba di area terbuka lalu ulangi.');
        }

        $locations = AttendanceLocation::allowedFor($user);
        if ($locations->isEmpty()) {
            return $this->reject('Belum ada lokasi absen untuk Anda, hubungi admin.');
        }
        $measured = AttendanceLocation::measure($locations, $lat, $lng);
        $hit = $measured->first(fn ($m) => $m[1] <= $m[0]->radius_m);
        if (! $hit) {
            [$nearest, $meters] = $measured->first();

            return $this->reject('Anda di luar area absen — '.self::distance($meters).' dari '.$nearest->nama.'.');
        }
        [$location, $meters] = $hit;

        $now = now();
        $judged = $calendar->judge($user, $tipe, $now);
        $flags = $this->flags($user, $lat, $lng, $akurasi, $now);

        $wib = $now->copy()->timezone(BusinessDay::zone())->locale('id');
        $jpeg = AttendanceWatermark::apply((string) file_get_contents($request->file('foto')->getRealPath()), [
            mb_strtoupper($tipe->label()).' · '.$wib->translatedFormat('D, j M Y').' · '.$wib->format('H:i:s').' WIB',
            $judged['status']->text($judged['selisih_menit']).' · '.$location->nama.' ('.$meters.' m)',
            sprintf('%.6f, %.6f', $lat, $lng).' · ±'.round($akurasi).' m · '.$user->name,
        ]);
        $items = $tipe === AttendanceType::Masuk ? self::items($v['items'] ?? null) : [];
        if ($items === null) {
            return $this->reject('Jumlah barang tidak valid.');
        }
        if ($tipe === AttendanceType::Pulang && ($cash = CashHeld::for($user)) > 0) {
            return $this->reject('Setor dulu cash Rp '.number_format($cash, 0, ',', '.').' sebelum absen pulang.');
        }

        $path = 'attendances/'.Str::uuid().'.jpg';
        Storage::disk('s3')->put($path, $jpeg);

        try {
            $attendance = DB::transaction(function () use ($user, $tipe, $lat, $lng, $location, $meters, $akurasi, $judged, $flags, $path, $v, $items) {
                if ($items !== []) {
                    app(StockTransfers::class)->pickup($user, ActiveLine::current(), $items, 'Dibawa saat absen masuk');
                }
                if ($tipe === AttendanceType::Pulang) {
                    $this->giveBackEverything($user);
                }

                return Attendance::create([
                    'sales_id' => $user->id,
                    'tipe' => $tipe,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'attendance_location_id' => $location->id,
                    'jarak_m' => $meters,
                    'akurasi_m' => (int) round($akurasi),
                    ...$judged,
                    'flags' => $flags,
                    'foto_path' => $path,
                    'catatan' => $v['catatan'] ?? null,
                    'idempotency_key' => $v['idempotency_key'],
                ]);
            });
        } catch (ValidationException $e) {
            Storage::disk('s3')->delete($path);

            return $this->reject(collect($e->errors())->flatten()->first() ?? 'Stok gudang berubah, pilih ulang barangnya.');
        }

        // the shift's first and last GPS point
        if ($masuk = $tipe === AttendanceType::Masuk ? $attendance : \App\Support\Sales\Shift::today($user)) {
            \App\Support\Sales\Shift::record($masuk, [['lat' => $lat, 'lng' => $lng, 'acc' => $akurasi, 't' => now()->getTimestampMs()]]);
        }

        $hasil = $attendance->statusText();
        session()->flash('absen_ok', "Absen {$tipe->value} tercatat · {$hasil}".($tipe === AttendanceType::Pulang ? ' · barang dikembalikan ke gudang' : ($items ? ' · '.count($items).' barang dibawa' : '')));

        return response()->json(['id' => $attendance->id, 'status' => 'ok', 'hasil' => $hasil], 201);
    }

    /** Check-out: all carried stock back to the warehouse, store returns handed in apart, in every line. */
    private function giveBackEverything(User $user): void
    {
        $transfers = app(StockTransfers::class);
        foreach (BusinessLine::cases() as $line) {
            if (($carried = CarriedStock::for($user, $line)->all()) !== []) {
                $transfers->returnGoods($user, $line, $carried, 'Dikembalikan saat absen pulang');
            }
            if (($returns = Consignments::returnsHeld($user, $line)->all()) !== []) {
                $transfers->handInStoreReturns($user, $line, $returns);
            }
        }
    }

    /** @return array<int, int>|null product_id => qty > 0, or null when the payload is malformed */
    private static function items(?string $json): ?array
    {
        $items = [];
        foreach ((array) json_decode((string) ($json ?: '{}'), true) as $id => $qty) {
            $n = filter_var($qty, FILTER_VALIDATE_INT);
            if ($n === false || $n < 0 || ! ctype_digit((string) $id)) {
                return null;
            }
            if ($n > 0) {
                $items[(int) $id] = $n;
            }
        }

        return $items;
    }

    /** Heuristics: flagged for admin, never blocking. */
    private function flags(User $user, float $lat, float $lng, float $akurasi, Carbon $now): array
    {
        $flags = [];
        $last = Attendance::where('sales_id', $user->id)->whereNotNull('latitude')->latest('created_at')->latest('id')->first();
        if ($last) {
            $km = Geo::distanceKm($last->latitude, $last->longitude, $lat, $lng);
            $hours = max($last->created_at->diffInSeconds($now) / 3600, 1 / 3600);
            if ($km > 1 && $km / $hours > 150) {
                $flags[] = 'lompatan';
            }
            if (round($last->latitude, 6) === round($lat, 6) && round($last->longitude, 6) === round($lng, 6)) {
                $flags[] = 'koordinat_identik';
            }
        }
        if ($akurasi <= 1) {
            $flags[] = 'akurasi_janggal';
        }

        return $flags;
    }

    private static function distance(int $meters): string
    {
        return $meters >= 1000 ? number_format($meters / 1000, 1, ',', '.').' km' : $meters.' m';
    }

    private function reject(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 422);
    }
}

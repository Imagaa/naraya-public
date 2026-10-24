<?php

namespace App\Http\Controllers;

use App\Support\Sales\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /lokasi: GPS points the sales phone queued while the shift is open. */
class TrackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'points' => 'required|array|max:500',
            'points.*.lat' => 'required|numeric|between:-90,90',
            'points.*.lng' => 'required|numeric|between:-180,180',
            'points.*.acc' => 'required|numeric|min:0',
            'points.*.t' => 'required|integer|min:0',
        ]);

        $masuk = Shift::open($request->user());
        if (! $masuk) {
            return response()->json(['tracking' => false], 409); // checked out (or never in): the phone stops and drops its queue
        }

        return response()->json(['tracking' => true, 'stored' => Shift::record($masuk, $v['points'])]);
    }
}

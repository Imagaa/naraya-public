<?php

namespace App\Http\Controllers;

use App\Enums\BusinessLine;
use App\Support\ActiveLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** POST /lini on the admin and sales portals: switch the active business line, back to the same page. */
class LineController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['line' => ['required', Rule::enum(BusinessLine::class)]]);
        ActiveLine::set(BusinessLine::from($data['line']));

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;

class UnitController extends Controller
{
    /**
     * Listado optimizado para selectores.
     */
    public function select(): JsonResponse
    {
        $units = Unit::where('status', true)
            ->select('id', 'name', 'abbreviation')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $units]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Odp;
use Illuminate\Http\Request;

class FilterController extends Controller
{
    public function getOdpList()
    {
        $odps = Odp::orderBy('name')->get(['id', 'name']);
        return response()->json($odps);
    }
}

<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Services\Reports\CommandCenter;
use Illuminate\Http\Request;

class CommandCenterController extends Controller
{
    public function index(Request $request, CommandCenter $center)
    {
        $units = BusinessUnit::ordered()->get();
        $unit = $units->firstWhere('id', $request->integer('unit')) ?: null;
        $d = $center->build((string) $request->query('period', 'month'), $unit?->id);

        return view('command-center.index', ['d' => $d, 'attention' => $center->attention($d), 'periods' => CommandCenter::PERIODS,
            'unitList' => $units->count() > 1 ? $units : collect(), 'unit' => $unit]);
    }
}

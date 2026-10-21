<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Results\ResultDisplay;
use Illuminate\Http\Request;

/**
 * Admin → Result display: what parents and students see on the portal's
 * result pages and in the mobile app (subject columns, totals, averages,
 * positions, GPA, comments, report card download) and whether they may
 * see results at all.
 */
class ResultDisplayController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:Manage result display');
    }

    public function index()
    {
        return view('admin.result-display.index', [
            'pagetitle' => 'Result display',
            's'         => ResultDisplay::settings(),
            'catalogue' => ResultDisplay::catalogue(),
            'model'     => ResultDisplay::scoreModel(),
        ]);
    }

    public function save(Request $request)
    {
        $request->validate([
            'position_basis' => 'required|in:cum,term',
            'subject'        => 'nullable|array',
            'summary'        => 'nullable|array',
        ]);

        ResultDisplay::save([
            'parents_can_view'  => $request->boolean('parents_can_view'),
            'students_can_view' => $request->boolean('students_can_view'),
            'position_basis'    => $request->input('position_basis'),
            'subject'           => (array) $request->input('subject', []),
            'summary'           => (array) $request->input('summary', []),
        ], $request->user()->id ?? null);

        return redirect()->route('result-display.index')->with('success', 'Saved. Parents, students and the mobile app now see results this way.');
    }
}

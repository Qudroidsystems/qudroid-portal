<?php

namespace App\Http\Controllers\Checkin;

use App\Http\Controllers\Controller;
use App\Models\Checkin\Place;
use App\Services\Checkin\CheckinService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Office locations for staff clock-in, and the monthly staff attendance sheet. */
class PlaceController extends Controller
{
    public function __construct(protected CheckinService $checkin) {}

    public function index()
    {
        return view('modules.checkin.places', ['places' => Place::orderBy('name')->get()]);
    }

    protected function data(Request $request): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'], 'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius_m' => ['required', 'integer', 'between:20,5000'], 'work_starts' => ['required', 'date_format:H:i'], 'work_ends' => ['required', 'date_format:H:i', 'after:work_starts'],
            'grace_minutes' => ['required', 'integer', 'between:0,240'], 'workdays' => ['required', 'array', 'min:1'], 'workdays.*' => ['integer', 'between:1,7'],
            'require_location' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'],
        ], ['workdays.required' => 'Pick the working days.']);
        $d['workdays'] = array_values(array_unique(array_map('intval', $d['workdays'])));
        sort($d['workdays']);
        $d['require_location'] = $request->boolean('require_location') && isset($d['lat']);
        $d['is_active'] = $request->boolean('is_active', true);

        return $d;
    }

    public function store(Request $request)
    {
        $p = Place::create($this->data($request));

        return back()->with('success', $p->name . ' added. Put its door QR on a screen at the entrance.');
    }

    public function update(Request $request, Place $place)
    {
        $place->update($this->data($request));
        // Today's clock-in follows the new hours if nobody has clocked in yet.
        $today = $place->events()->whereDate('day', today())->first();
        if ($today && !$today->records()->exists()) $today->delete();

        return back()->with('success', $place->name . ' saved.');
    }

    /** Today's door QR for this office. */
    public function screen(Place $place)
    {
        abort_unless($place->is_active, 404);
        $event = $this->checkin->staffDay($place);

        return redirect()->route('checkin.events.screen', $event);
    }

    public function today(Place $place)
    {
        return redirect()->route('checkin.events.show', $this->checkin->staffDay($place));
    }

    public function report(Request $request)
    {
        $month = $request->filled('month') ? Carbon::createFromFormat('Y-m', $request->query('month'))->startOfMonth() : today()->startOfMonth();
        $place = $request->filled('place') ? Place::find($request->integer('place')) : null;
        $r = $this->checkin->staffMonth($month, $place);

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($r) {
                $out = fopen('php://output', 'w');
                $days = [];
                for ($d = $r['from']->copy(); $d->lte($r['to']); $d->addDay()) $days[] = $d->toDateString();
                fputcsv($out, array_merge(['Name', 'Days in', 'Days late', 'Average arrival', 'Hours'], $days));
                foreach ($r['rows'] as $row) {
                    fputcsv($out, array_merge([$row['user']?->name, $row['present'], $row['late'], $row['avg_in'], $row['hours']],
                        array_map(fn ($d) => isset($row['days'][$d]) ? $row['days'][$d]['in'] . '-' . ($row['days'][$d]['out'] ?? '') . ($row['days'][$d]['late'] ? ' L' : '') : '', $days)));
                }
                fclose($out);
            }, 'staff-attendance-' . $month->format('Y-m') . '.csv', ['Content-Type' => 'text/csv']);
        }

        return view('modules.checkin.staff', $r + ['month' => $month, 'place' => $place, 'places' => Place::orderBy('name')->get()]);
    }
}

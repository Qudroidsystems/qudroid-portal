<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Calendar\CalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** School calendar events and holidays the user is allowed to see. */
class CalendarController extends ApiController
{
    public function __construct(protected CalendarService $svc) {}

    public function index(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->filled('from') ? Carbon::parse($request->from) : Carbon::today()->startOfMonth();
        $to   = $request->filled('to') ? Carbon::parse($request->to) : $from->copy()->addMonths(2)->endOfMonth();
        if ($from->diffInDays($to) > 400) $to = $from->copy()->addDays(400);

        $user = $request->user();
        $manage = false;
        try { $manage = $user->can('Manage school calendar'); } catch (\Throwable $e) {}

        $items = $this->svc->occurrences($from, $to, [
            'audiences'     => $manage ? null : $this->svc->viewerGroups($user),
            'with_holidays' => $request->query('holidays', '1') !== '0',
        ]);

        return $this->ok($items->map(fn ($e) => [
            'id'          => (string) $e->id,
            'event_id'    => $e->event_id,
            'title'       => $e->title,
            'description' => $e->description,
            'category'    => $e->category,
            'color'       => $e->color,
            'location'    => $e->location,
            'kind'        => $e->kind,
            'all_day'     => (bool) $e->all_day,
            'start_date'  => $e->start->toDateString(),
            'end_date'    => $e->end->toDateString(),
            'start_time'  => $e->start_time,
            'end_time'    => $e->end_time,
        ])->values(), null, 200, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);
    }
}

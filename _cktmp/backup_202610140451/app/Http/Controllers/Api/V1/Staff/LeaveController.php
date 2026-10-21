<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\Leave\LeaveService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Staff leave: balances, my requests, apply, cancel. Same rules as the portal. */
class LeaveController extends ApiController
{
    public function __construct(protected LeaveService $svc) {}

    public function index(Request $request)
    {
        $s = $this->svc->staffFor($request->user()->id);
        if (!$s) return $this->fail('No staff record is linked to your account.', 404, 'no_staff_record');

        $year = (int) ($request->get('year') ?: now()->year);

        $mine = DB::table('leave_requests as r')->join('leave_types as t', 't.id', '=', 'r.leave_type_id')
            ->leftJoin('users as rl', 'rl.id', '=', 'r.relief_staff_id')
            ->where('r.staff_id', $s->id)->orderByDesc('r.start_date')->limit(50)
            ->get(['r.*', 't.name as type', 't.color', 'rl.name as relief_name']);

        return $this->ok([
            'year'     => $year,
            'balances' => $this->svc->balances((int) $s->id, $year, $s->gender ?? null)->map(fn ($b) => [
                'leave_type_id' => (int) $b->id, 'name' => $b->name, 'color' => $b->color ?? null,
                'paid' => (bool) ($b->paid ?? true), 'requires_document' => (bool) ($b->requires_document ?? false),
                'entitled' => (float) $b->entitled, 'approved' => (float) $b->approved, 'pending' => (float) $b->pending,
                'limited' => (bool) $b->limited, 'remaining' => $b->remaining,
            ])->values(),
            'requests'   => $mine->map(fn ($r) => self::present($r))->values(),
            'colleagues' => DB::table('staffbioinfo as st')->join('users as u', 'u.id', '=', 'st.userid')
                ->where('st.id', '!=', $s->id)->orderBy('u.name')->get(['u.id', 'u.name']),
        ]);
    }

    public function store(Request $request)
    {
        $s = $this->svc->staffFor($request->user()->id);
        if (!$s) return $this->fail('No staff record is linked to your account.', 404, 'no_staff_record');

        $d = $request->validate([
            'leave_type_id'   => 'required|exists:leave_types,id',
            'start_date'      => 'required|date|after_or_equal:' . now()->subDays(14)->toDateString(),
            'end_date'        => 'required|date|after_or_equal:start_date',
            'half_day'        => 'nullable|boolean',
            'reason'          => 'required|string|min:5|max:2000',
            'relief_staff_id' => 'nullable|exists:users,id',
            'contact_phone'   => 'nullable|string|max:30',
            'attachment'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'handover_note'   => 'nullable|string|max:3000',
        ]);

        $type = DB::table('leave_types')->find($d['leave_type_id']);
        $d['days'] = $this->svc->workingDays($d['start_date'], $d['end_date'], $request->boolean('half_day'));
        if ($d['days'] <= 0) return $this->fail('Those dates are all weekends or school holidays.', 422, 'no_working_days');
        if ($this->svc->overlaps((int) $s->id, $d['start_date'], $d['end_date'])) {
            return $this->fail('You already have leave requested or approved for some of those days.', 422, 'overlap');
        }
        if ($type->requires_document && !$request->hasFile('attachment')) {
            return $this->fail($type->name . ' needs a supporting document (e.g. medical note).', 422, 'document_required');
        }
        $bal = $this->svc->balances((int) $s->id, Carbon::parse($d['start_date'])->year, $s->gender ?? null)->firstWhere('id', $type->id);
        if ($bal && $bal->limited && $d['days'] > $bal->remaining) {
            return $this->fail("You have {$bal->remaining} day(s) of {$type->name} left this year, but asked for {$d['days']}.", 422, 'insufficient_balance');
        }
        if ($request->hasFile('attachment')) $d['attachment'] = $request->file('attachment')->store('leave/' . $s->id, 'local');
        $d['half_day'] = $request->boolean('half_day');

        $id = $this->svc->submit($s, $d);
        if (!empty($d['handover_note']) && Schema::hasColumn('leave_requests', 'handover_note')) {
            DB::table('leave_requests')->where('id', $id)->update(['handover_note' => $d['handover_note']]);
        }

        $row = DB::table('leave_requests as r')->join('leave_types as t', 't.id', '=', 'r.leave_type_id')
            ->leftJoin('users as rl', 'rl.id', '=', 'r.relief_staff_id')->where('r.id', $id)
            ->first(['r.*', 't.name as type', 't.color', 'rl.name as relief_name']);

        return $this->ok(self::present($row), "Leave request sent ({$d['days']} working day(s)). You'll be notified when it's decided.", 201);
    }

    public function cancel(Request $request, int $id)
    {
        $s = $this->svc->staffFor($request->user()->id);
        $r = $s ? DB::table('leave_requests')->where('id', $id)->where('staff_id', $s->id)->first() : null;
        if (!$r) return $this->fail('Leave request not found.', 404, 'not_found');

        $can = in_array($r->status, ['pending_hod', 'pending_principal'], true)
            || ($r->status === 'approved' && Carbon::parse($r->start_date)->isFuture());
        if (!$can) return $this->fail('This leave can no longer be cancelled here. Ask the principal.', 422, 'closed');

        DB::table('leave_requests')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);

        return $this->ok(null, 'Leave request cancelled.');
    }

    public static function statusLabel(string $status): string
    {
        return [
            'pending_hod' => 'Waiting for HOD', 'pending_principal' => 'Waiting for principal',
            'approved' => 'Approved', 'rejected' => 'Declined', 'cancelled' => 'Cancelled',
        ][$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function present(object $r): array
    {
        return [
            'id'            => (int) $r->id,
            'type'          => $r->type ?? null,
            'color'         => $r->color ?? null,
            'start_date'    => $r->start_date,
            'end_date'      => $r->end_date,
            'half_day'      => (bool) $r->half_day,
            'days'          => (float) $r->days,
            'reason'        => $r->reason,
            'status'        => $r->status,
            'status_label'  => self::statusLabel((string) $r->status),
            'relief_name'   => $r->relief_name ?? null,
            'hod_note'      => $r->hod_note ?? null,
            'approver_note' => $r->approver_note ?? null,
            'created_at'    => $r->created_at,
        ];
    }
}

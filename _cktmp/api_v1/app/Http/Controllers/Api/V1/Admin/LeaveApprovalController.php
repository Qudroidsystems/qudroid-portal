<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithApi;
use App\Http\Controllers\Api\V1\StudentLeaveController as ApiStudentLeave;
use App\Http\Controllers\Api\V1\Staff\LeaveController as ApiStaffLeave;
use App\Http\Controllers\LeaveController as WebLeaveController;
use App\Models\StudentLeaveRequest;
use App\Services\Leave\StudentLeaveService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Leave approvals from the app: staff leave (HOD recommends, principal
 * approves) and student leave (class teacher recommends, principal approves).
 * Uses the web controller's queue so both show the same requests.
 */
class LeaveApprovalController extends WebLeaveController
{
    use RespondsWithApi;

    // ── Staff leave ─────────────────────────────────────────────────────

    public function staffQueue(Request $request)
    {
        $u = $request->user();
        if (!$this->canAny($u, ['Recommend leave', 'Approve leave'])) return $this->fail('Not allowed.', 403, 'forbidden');

        $pending = $this->queueQuery($request)->join('users as u', 'u.id', '=', 'r.user_id')
            ->join('leave_types as t', 't.id', '=', 'r.leave_type_id')
            ->leftJoin('users as rl', 'rl.id', '=', 'r.relief_staff_id')
            ->orderBy('r.start_date')
            ->get(['r.*', 'u.name as staff_name', 's.department', 't.name as type', 't.color', 'rl.name as relief_name']);

        return $this->ok($pending->map(function ($p) {
            $b = $this->svc->balances((int) $p->staff_id, Carbon::parse($p->start_date)->year)->firstWhere('id', $p->leave_type_id);
            return ApiStaffLeave::present($p) + [
                'staff_name' => $p->staff_name,
                'department' => $p->department,
                'remaining'  => $b?->remaining,
                'stage'      => $p->status === 'pending_hod' ? 'recommend' : 'approve',
            ];
        })->values());
    }

    /** POST admin/leave/{id}/decide { decision: yes|no, note? } */
    public function staffDecide(Request $request, int $id)
    {
        $d = $request->validate(['decision' => 'required|in:yes,no', 'note' => 'nullable|string|max:500']);
        if ($d['decision'] === 'no' && empty($d['note'])) return $this->fail('Please give a reason when declining.', 422, 'note_required');

        $r = DB::table('leave_requests')->find($id);
        if (!$r) return $this->fail('Leave request not found.', 404, 'not_found');

        $u = $request->user();
        $ok = $d['decision'] === 'yes';

        if ($r->status === 'pending_principal' || ($r->status === 'pending_hod' && $u->can('Approve leave'))) {
            if (!$u->can('Approve leave')) return $this->fail('Not allowed.', 403, 'forbidden');
            $this->svc->decide($r, $u->id, $ok, $d['note'] ?? null);
            return $this->ok(null, $ok ? 'Leave approved.' : 'Leave declined.');
        }
        if ($r->status === 'pending_hod') {
            if (!$u->can('Recommend leave') || (int) $r->user_id === (int) $u->id) return $this->fail('Not allowed.', 403, 'forbidden');
            $this->svc->recommend($r, $u->id, $ok, $d['note'] ?? null);
            return $this->ok(null, $ok ? 'Recommended — sent to the principal.' : 'Not recommended.');
        }

        return $this->fail('This request has already been decided.', 422, 'closed');
    }

    // ── Student leave ───────────────────────────────────────────────────

    public function studentQueue(Request $request)
    {
        $u = $request->user();
        $canApprove = $this->canAny($u, ['Approve student leave']);
        $canRecommend = $this->canAny($u, ['Recommend student leave']);
        if (!$canApprove && !$canRecommend) return $this->fail('Not allowed.', 403, 'forbidden');

        $myClassIds = DB::table('classteacher')->where('staffid', $u->id)->pluck('schoolclassid')->all();

        $pending = StudentLeaveRequest::with('student')
            ->where(function ($q) use ($canApprove, $canRecommend, $myClassIds) {
                $q->whereRaw('1=0');
                if ($canApprove) $q->orWhere('status', 'pending_principal');
                if ($canRecommend && $myClassIds) $q->orWhere(fn ($w) => $w->where('status', 'pending_teacher')->whereIn('class_id', $myClassIds));
            })
            ->orderBy('start_date')->get();

        $classes = DB::table('schoolclass as c')->leftJoin('schoolarm as a', 'a.id', '=', 'c.arm')
            ->whereIn('c.id', $pending->pluck('class_id')->filter()->unique())
            ->pluck(DB::raw("TRIM(CONCAT(COALESCE(c.schoolclass,''), ' ', COALESCE(a.arm,'')))"), 'c.id');

        return $this->ok($pending->map(fn ($r) => ApiStudentLeave::present($r) + [
            'class_name' => $classes[$r->class_id] ?? null,
            'stage'      => $r->status === 'pending_teacher' ? 'recommend' : 'approve',
        ])->values());
    }

    public function studentDecide(Request $request, int $id, StudentLeaveService $svc)
    {
        $d = $request->validate(['decision' => 'required|in:yes,no', 'note' => 'nullable|string|max:500']);
        $leave = StudentLeaveRequest::find($id);
        if (!$leave) return $this->fail('Leave request not found.', 404, 'not_found');

        $u = $request->user();
        $ok = $d['decision'] === 'yes';

        if ($leave->status === 'pending_teacher') {
            $mine = DB::table('classteacher')->where('staffid', $u->id)->where('schoolclassid', $leave->class_id)->exists();
            if (!$mine || !$u->can('Recommend student leave')) return $this->fail("Only this class's teacher can recommend.", 403, 'forbidden');
            $svc->recommend($leave, (int) $u->id, $ok, $d['note'] ?? null);
            return $this->ok(null, $ok ? 'Recommended — sent to the principal.' : 'Marked as not recommended.');
        }
        if ($leave->status === 'pending_principal') {
            if (!$u->can('Approve student leave')) return $this->fail('Not allowed.', 403, 'forbidden');
            $svc->decide($leave, (int) $u->id, $ok, $d['note'] ?? null);
            return $this->ok(null, $ok ? 'Leave approved.' : 'Leave declined.');
        }

        return $this->fail('This request has already been decided.', 422, 'closed');
    }

    protected function canAny($user, array $perms): bool
    {
        foreach ($perms as $p) {
            try { if ($user->can($p)) return true; } catch (\Throwable $e) {}
        }
        return false;
    }
}

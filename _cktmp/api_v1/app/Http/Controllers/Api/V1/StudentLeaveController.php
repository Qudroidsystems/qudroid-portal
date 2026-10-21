<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Student;
use App\Models\StudentLeaveRequest;
use App\Services\Api\StudentAccess;
use App\Services\Leave\StudentLeaveService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Leave of absence for a student, asked by the parent or the student.
 * Class teacher recommends, principal approves (same flow as the portal).
 */
class StudentLeaveController extends ApiController
{
    public function __construct(protected StudentLeaveService $svc) {}

    public function index(Request $request)
    {
        $ids = StudentAccess::mine($request->user())->pluck('id')->map(fn ($v) => (int) $v)->all();

        $rows = StudentLeaveRequest::with('student')->whereIn('student_id', $ids ?: [0])
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', (int) $request->student_id))
            ->latest()->limit(100)->get();

        return $this->ok($rows->map(fn ($r) => self::present($r))->values(), null, 200, [
            'reasons' => StudentLeaveRequest::REASONS,
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'student_id'    => 'required|integer',
            'reason_type'   => 'required|in:' . implode(',', array_keys(StudentLeaveRequest::REASONS)),
            'reason'        => 'required|string|min:5|max:1000',
            'start_date'    => 'required|date|after_or_equal:today',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'contact_phone' => 'nullable|string|max:30',
            'attachment'    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $user = $request->user();
        $studentId = (int) $d['student_id'];
        if (!StudentAccess::isOwnOrChild($user, $studentId)) {
            return $this->fail('You cannot request leave for that student.', 403, 'forbidden');
        }
        $student = Student::find($studentId);
        if (!$student) return $this->fail('Student not found.', 404, 'not_found');

        if (Carbon::parse($d['end_date'])->diffInDays(Carbon::parse($d['start_date']), true) > 120) {
            return $this->fail('A single leave request cannot be longer than about 4 months.', 422, 'too_long');
        }
        if ($this->svc->overlaps($studentId, $d['start_date'], $d['end_date'])) {
            return $this->fail('This student already has leave requested or approved that overlaps those dates.', 422, 'overlap');
        }
        if ($request->hasFile('attachment')) {
            $d['attachment'] = $request->file('attachment')->store('student-leave', 'local');
        }

        $type = (int) ($user->student_id ?? 0) === $studentId ? 'student' : 'parent';
        $req = $this->svc->submit($student, $d, (int) $user->id, $type);

        return $this->ok(self::present($req->load('student')), 'Leave request sent. You will be notified when the school decides.', 201);
    }

    public function cancel(Request $request, int $leave)
    {
        $req = StudentLeaveRequest::find($leave);
        if (!$req || !StudentAccess::isOwnOrChild($request->user(), (int) $req->student_id)) {
            return $this->fail('Leave request not found.', 404, 'not_found');
        }
        if (!$req->isOpen()) return $this->fail('This request has already been decided.', 422, 'closed');

        $req->update(['status' => 'cancelled']);
        return $this->ok(self::present($req->fresh('student')), 'Leave request withdrawn.');
    }

    public static function present(StudentLeaveRequest $r): array
    {
        $s = $r->student;
        return [
            'id'             => $r->id,
            'student_id'     => (int) $r->student_id,
            'student_name'   => $s ? trim($s->firstname . ' ' . $s->lastname) : null,
            'reason_type'    => $r->reason_type,
            'reason_label'   => $r->reasonLabel(),
            'reason'         => $r->reason,
            'start_date'     => optional($r->start_date)->toDateString(),
            'end_date'       => optional($r->end_date)->toDateString(),
            'days'           => (int) $r->days,
            'status'         => $r->status,
            'status_label'   => $r->label()[0] ?? $r->status,
            'is_open'        => $r->isOpen(),
            'requester_type' => $r->requester_type,
            'teacher_note'   => $r->teacher_note,
            'approver_note'  => $r->approver_note,
            'has_attachment' => (bool) $r->attachment,
            'created_at'     => optional($r->created_at)->toIso8601String(),
        ];
    }
}

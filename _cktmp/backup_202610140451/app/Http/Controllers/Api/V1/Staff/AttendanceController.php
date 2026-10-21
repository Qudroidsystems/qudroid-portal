<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithApi;
use App\Http\Controllers\AttendanceController as WebAttendanceController;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceSummary;
use App\Models\AttendanceTermSetting;
use App\Models\StudentAttendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class-teacher attendance register from the app. Saving goes through the
 * web controller (same backdating rules and summary rebuild); this class
 * adds the check that the user is the class teacher for that class/term.
 */
class AttendanceController extends WebAttendanceController
{
    use RespondsWithApi;

    /** Classes this user is class teacher of (default: current session). */
    public function classes(Request $request)
    {
        $sessionId = (int) ($request->get('session_id') ?: $this->currentSessionId());

        $rows = DB::table('classteacher as ct')
            ->join('schoolclass as c', 'c.id', '=', 'ct.schoolclassid')
            ->leftJoin('schoolarm as a', 'a.id', '=', 'c.arm')
            ->join('schoolterm as t', 't.id', '=', 'ct.termid')
            ->join('schoolsession as s', 's.id', '=', 'ct.sessionid')
            ->where('ct.staffid', $request->user()->id)
            ->when($sessionId, fn ($q) => $q->where('ct.sessionid', $sessionId))
            ->orderBy('t.id')->orderBy('c.schoolclass')->orderBy('a.arm')
            ->get(['ct.schoolclassid as class_id', 'ct.termid as term_id', 'ct.sessionid as session_id', 't.term', 's.session',
                   DB::raw("TRIM(CONCAT(COALESCE(c.schoolclass,''), ' ', COALESCE(a.arm,''))) as class_name")]);

        $counts = DB::table('studentclass')->whereIn('schoolclassid', $rows->pluck('class_id')->unique())
            ->when($sessionId, fn ($q) => $q->where('sessionid', $sessionId))
            ->groupBy('schoolclassid')->pluck(DB::raw('COUNT(DISTINCT studentId)'), 'schoolclassid');

        return $this->ok($rows->map(fn ($r) => [
            'class_id'   => (int) $r->class_id,
            'class_name' => $r->class_name,
            'term_id'    => (int) $r->term_id,
            'term'       => $r->term,
            'session_id' => (int) $r->session_id,
            'session'    => $r->session,
            'students'   => (int) ($counts[$r->class_id] ?? 0),
            'is_current_term' => (int) $r->term_id === (int) $this->currentTermId(),
        ])->values());
    }

    /** GET staff/attendance/register?class_id&term_id&session_id&date&period */
    public function registerSheet(Request $request)
    {
        $d = $request->validate([
            'class_id'   => 'required|integer',
            'term_id'    => 'required|integer',
            'session_id' => 'required|integer',
            'date'       => 'nullable|date',
            'period'     => 'nullable|in:morning,afternoon',
        ]);
        [$classId, $termId, $sessionId] = [(int) $d['class_id'], (int) $d['term_id'], (int) $d['session_id']];
        if ($deny = $this->denyClass($classId, $termId, $sessionId)) return $deny;

        $setting = AttendanceTermSetting::where('term_id', $termId)->where('session_id', $sessionId)->first();
        if (!$setting) {
            return $this->fail('Attendance has not been configured for this term. Please ask admin to set it up.', 422, 'not_configured');
        }

        $date = Carbon::parse($d['date'] ?? today())->toDateString();
        $period = $d['period'] ?? 'morning';
        if ($period === 'afternoon' && !$setting->track_afternoon) $period = 'morning';

        $students = DB::table('studentclass as sc')
            ->join('studentRegistration as s', 's.id', '=', 'sc.studentId')
            ->leftJoin('studentpicture as p', 'p.studentid', '=', 's.id')
            ->where('sc.schoolclassid', $classId)->where('sc.sessionid', $sessionId)
            ->orderBy('s.lastname')->orderBy('s.firstname')
            ->get(['s.id', 's.admissionNo', 's.firstname', 's.lastname', 's.othername', 's.gender', 'p.picture'])
            ->unique('id');

        $existing = StudentAttendance::where(['schoolclass_id' => $classId, 'term_id' => $termId, 'session_id' => $sessionId,
            'attendance_date' => $date, 'period' => $period])->get(['student_id', 'status', 'notes'])->keyBy('student_id');

        $summaries = AttendanceSummary::where(['schoolclass_id' => $classId, 'term_id' => $termId, 'session_id' => $sessionId])
            ->get()->keyBy('student_id');

        $backdate = $this->backdateError($termId, $sessionId, $date);

        return $this->ok([
            'class_id'   => $classId,
            'term_id'    => $termId,
            'session_id' => $sessionId,
            'date'       => $date,
            'period'     => $period,
            'periods'    => array_values(array_filter(['morning', $setting->track_afternoon ? 'afternoon' : null])),
            'is_holiday' => $this->holiday($date, $termId, $sessionId),
            'editable'   => $backdate === null,
            'note'       => $backdate,
            'term_start' => optional($setting->resumption_date)->toDateString(),
            'term_end'   => optional($setting->vacation_date)->toDateString(),
            'statuses'   => ['present', 'absent', 'late', 'excused', 'sick_leave'],
            'marked'     => $existing->count(),
            'students'   => $students->map(fn ($s) => [
                'id'           => (int) $s->id,
                'admission_no' => $s->admissionNo,
                'first_name'   => $s->firstname,
                'last_name'    => $s->lastname,
                'other_name'   => $s->othername,
                'gender'       => $s->gender,
                'photo_url'    => $this->studentPhotoUrl($s->picture),
                'status'       => $existing[$s->id]->status ?? null,
                'notes'        => $existing[$s->id]->notes ?? null,
                'term_rate'    => isset($summaries[$s->id]) ? (float) $summaries[$s->id]->attendance_percentage : null,
            ])->values(),
        ]);
    }

    /**
     * POST staff/attendance
     * { schoolclass_id, term_id, session_id, attendance_date, period, records: [{student_id, status, notes?}] }
     */
    public function saveRegister(Request $request)
    {
        if ($deny = $this->denyFromBody($request)) return $deny;
        return $this->wrap($this->save($request));
    }

    /** POST staff/attendance/mark-all-present { schoolclass_id, term_id, session_id, attendance_date, period, student_ids[] } */
    public function allPresent(Request $request)
    {
        if ($deny = $this->denyFromBody($request)) return $deny;
        return $this->wrap($this->markAllPresent($request));
    }

    // ── helpers ─────────────────────────────────────────────────────────

    protected function denyFromBody(Request $request): ?JsonResponse
    {
        $request->validate([
            'schoolclass_id' => 'required|integer',
            'term_id'        => 'required|integer',
            'session_id'     => 'required|integer',
        ]);
        return $this->denyClass((int) $request->schoolclass_id, (int) $request->term_id, (int) $request->session_id);
    }

    protected function denyClass(int $classId, int $termId, int $sessionId): ?JsonResponse
    {
        if ($this->isAttendanceAdmin()) return null;
        $isClassTeacher = DB::table('classteacher')->where('staffid', auth()->id())
            ->where('schoolclassid', $classId)->where('termid', $termId)->where('sessionid', $sessionId)->exists();
        return $isClassTeacher ? null : $this->fail('You are not the class teacher for this class.', 403, 'forbidden');
    }

    protected function holiday(string $date, int $termId, int $sessionId): bool
    {
        return AttendanceHoliday::where('term_id', $termId)->where('session_id', $sessionId)
            ->where(fn ($q) => $q->where(fn ($qq) => $qq->whereNull('holiday_end_date')->where('holiday_date', $date))
                ->orWhere(fn ($qq) => $qq->where('holiday_date', '<=', $date)->where('holiday_end_date', '>=', $date)))
            ->exists();
    }

    protected function wrap(JsonResponse $res): JsonResponse
    {
        $body = $res->getData(true);
        if (!empty($body['success'])) return $this->ok($body['summary'] ?? null, $body['message'] ?? 'Saved.');
        $status = $res->getStatusCode() < 400 ? 422 : $res->getStatusCode();
        return $this->fail($body['message'] ?? 'Could not save attendance.', $status, 'save_failed');
    }
}

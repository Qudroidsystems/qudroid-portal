<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Schoolsession;
use App\Models\Schoolterm;
use App\Services\Reporting\ManagementDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Management overview for the principal / proprietor — the same service as
 * the portal's Management Dashboard. Needs "View management dashboard".
 */
class DashboardController extends ApiController
{
    public function __construct(protected ManagementDashboardService $svc) {}

    public function index(Request $request)
    {
        if (!$this->allowed($request)) return $this->fail('Not allowed.', 403, 'forbidden');

        $sessionId = (int) ($request->get('session_id') ?: $this->currentSessionId() ?: Schoolsession::max('id'));
        $termId    = (int) ($request->get('term_id') ?: $this->currentTermId() ?: Schoolterm::min('id'));

        $att  = $this->svc->attendanceToday($sessionId, $termId);
        $fees = $this->svc->feeSnapshot($termId, $sessionId, $request->boolean('refresh'));
        $res  = $this->svc->results($termId, $sessionId);
        $msg  = $this->svc->messaging();

        // Actions carry web links/icons; keep the text and level for the app.
        $actions = collect($this->svc->actions($att, $fees, $res, $msg))->map(fn ($a) => [
            'level' => $a[0] ?? 'info', 'text' => $a[2] ?? '', 'web_url' => $a[3] ?? null,
        ])->values();

        unset($att['unmarked_list']);

        return $this->ok([
            'session_id'  => $sessionId,
            'term_id'     => $termId,
            'enrolment'   => $this->svc->enrolment($sessionId),
            'attendance'  => $att,
            'fees'        => $fees,           // null while it is being calculated — call again shortly
            'fees_ready'  => $fees !== null,
            'collections' => $this->svc->collections(14),
            'results'     => $res,
            'messaging'   => $msg,
            'actions'     => $actions,
        ]);
    }

    /** Student look-up by name or admission number. Needs "View student". */
    public function searchStudents(Request $request)
    {
        $u = $request->user();
        $ok = false;
        try { $ok = $u->can('View student') || $u->can('Show Student'); } catch (\Throwable $e) {}
        if (!$ok) return $this->fail('Not allowed.', 403, 'forbidden');

        $q = trim((string) $request->get('q'));
        if (mb_strlen($q) < 2) return $this->ok([]);

        $sessionId = $this->currentSessionId();
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

        $rows = DB::table('studentRegistration as s')
            ->leftJoin('studentclass as sc', fn ($j) => $j->on('sc.studentId', '=', 's.id')->when($sessionId, fn ($jj) => $jj->where('sc.sessionid', $sessionId)))
            ->leftJoin('schoolclass as c', 'c.id', '=', 'sc.schoolclassid')
            ->leftJoin('schoolarm as a', 'a.id', '=', 'c.arm')
            ->leftJoin('studentpicture as p', 'p.studentid', '=', 's.id')
            ->where(fn ($w) => $w->where('s.admissionNo', 'like', $like)->orWhere('s.firstname', 'like', $like)
                ->orWhere('s.lastname', 'like', $like)
                ->orWhereRaw("CONCAT(COALESCE(s.firstname,''), ' ', COALESCE(s.lastname,'')) like ?", [$like]))
            ->orderBy('s.lastname')->limit(30)
            ->get(['s.id', 's.admissionNo', 's.firstname', 's.lastname', 's.othername', 's.student_status', 'p.picture',
                   DB::raw("TRIM(CONCAT(COALESCE(c.schoolclass,''), ' ', COALESCE(a.arm,''))) as class_name")])
            ->unique('id');

        return $this->ok($rows->map(fn ($s) => [
            'id'           => (int) $s->id,
            'admission_no' => $s->admissionNo,
            'full_name'    => trim(implode(' ', array_filter([$s->firstname, $s->othername, $s->lastname]))),
            'class_name'   => $s->class_name ?: null,
            'status'       => $s->student_status,
            'photo_url'    => $this->studentPhotoUrl($s->picture),
        ])->values());
    }

    protected function allowed(Request $request): bool
    {
        try { return $request->user()->can('View management dashboard'); } catch (\Throwable $e) { return false; }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "My profile" for the mobile app: the signed-in user's own details, employment
 * details, roles and (for teaching staff) what they teach this session.
 *
 * Returned as ready-to-display sections so the app needs no changes when a field
 * is added here: sections[] = {title, items[] = {label, value}}.
 */
class ProfileController extends ApiController
{
    public function show(Request $request)
    {
        $user = $request->user();
        $s = Schema::hasTable('staffbioinfo') ? DB::table('staffbioinfo')->where('userid', $user->id)->first() : null;

        $sections = [];

        // ── Account / personal ───────────────────────────────────────────
        $sections[] = $this->section('Personal details', [
            'Full name'      => $user->name,
            'Title'          => $s->title ?? null,
            'Email'          => $this->realEmail($user->email) ?: ($s->email ?? null),
            'Phone'          => $user->phone_number ?: ($s->phonenumber ?? null),
            'Gender'         => $this->pretty($s->gender ?? null),
            'Date of birth'  => $this->date($s->dateofbirth ?? null),
            'Marital status' => $this->pretty($s->maritalstatus ?? null),
            'Address'        => $s->address ?? null,
            'State'          => $s->state ?? null,
            'Local government' => $s->local ?? null,
            'Nationality'    => $s->nationality ?? null,
        ]);

        // ── Employment ───────────────────────────────────────────────────
        $employmentType = null;
        if ($s && Schema::hasTable('staff_pay_profiles')) {
            $employmentType = DB::table('staff_pay_profiles')->where('staff_id', $s->id)->value('employment_type');
        }
        if ($s) {
            $sections[] = $this->section('Employment', [
                'Staff ID'            => $s->employmentid ?? null,
                'Job title'           => $s->job_title ?? null,
                'Department'          => $s->department ?? null,
                'Position'            => $s->position ?? null,
                'Employment type'     => $this->pretty($employmentType),
                'Grade level'         => $s->grade_level ?? null,
                'Step'                => $s->step ?? null,
                'Date employed'       => $this->date($s->date_of_employment ?? null),
                'Date confirmed'      => $this->date($s->date_of_confirmation ?? null),
                'Years of service'    => $this->years($s->date_of_employment ?? null),
                'Highest qualification' => $s->qualification ?? null,
                'Status'              => $this->pretty($s->status ?? null),
            ]);
        }

        // ── Roles & access ───────────────────────────────────────────────
        $roles = $this->safe(fn () => $user->getRoleNames()->values()->all(), []);
        $sections[] = [
            'title' => 'Roles & access',
            'items' => [['label' => $roles && count($roles) > 1 ? 'Roles' : 'Role', 'value' => $roles ? implode(', ', $roles) : 'No role assigned']],
        ];

        // ── Teaching this session ────────────────────────────────────────
        if ($s) {
            $sessionId = $this->currentSessionId();
            $subjects = $this->safe(function () use ($user, $sessionId) {
                return DB::table('subjectteacher as st')
                    ->join('subject as s', 's.id', '=', 'st.subjectid')
                    ->leftJoin('subjectclass as sjc', 'sjc.subjectteacherid', '=', 'st.id')
                    ->leftJoin('schoolclass as sc', 'sc.id', '=', 'sjc.schoolclassid')
                    ->leftJoin('schoolarm as arm', 'arm.id', '=', 'sc.arm')
                    ->where('st.staffid', $user->id)
                    ->when($sessionId, fn ($q) => $q->where('st.sessionid', $sessionId))
                    ->get(['s.subject', 'sc.schoolclass', 'arm.arm'])
                    ->map(fn ($r) => trim($r->subject . ($r->schoolclass ? ' — ' . trim($r->schoolclass . ' ' . $r->arm) : '')))
                    ->unique()->values()->all();
            }, []);
            $classes = $this->safe(function () use ($user, $sessionId) {
                return DB::table('classteacher as ct')
                    ->join('schoolclass as c', 'c.id', '=', 'ct.schoolclassid')
                    ->leftJoin('schoolarm as a', 'a.id', '=', 'c.arm')
                    ->where('ct.staffid', $user->id)
                    ->when($sessionId, fn ($q) => $q->where('ct.sessionid', $sessionId))
                    ->get(['c.schoolclass', 'a.arm'])
                    ->map(fn ($r) => trim($r->schoolclass . ' ' . $r->arm))->unique()->values()->all();
            }, []);
            if ($subjects || $classes) {
                $sections[] = [
                    'title' => 'Teaching this session',
                    'items' => array_values(array_filter([
                        $classes ? ['label' => 'Class teacher of', 'value' => implode(', ', $classes)] : null,
                        $subjects ? ['label' => 'Subjects', 'value' => implode("\n", $subjects)] : null,
                    ])),
                ];
            }
        }

        // ── Qualifications ───────────────────────────────────────────────
        if (Schema::hasTable('staff_qualifications')) {
            $quals = DB::table('staff_qualifications')->where('user_id', $user->id)->orderByDesc('year_obtained')->get();
            if ($quals->isNotEmpty()) {
                $sections[] = [
                    'title' => 'Qualifications',
                    'items' => $quals->map(fn ($q) => [
                        'label' => trim(($q->qualification ?: 'Qualification') . ($q->year_obtained ? ' (' . $q->year_obtained . ')' : '')),
                        'value' => trim(implode(' — ', array_filter([$q->institution, $q->field_of_study]))) ?: '—',
                    ])->values()->all(),
                ];
            }
        }

        // ── Bank (masked) ────────────────────────────────────────────────
        if ($s) {
            $pp = Schema::hasTable('staff_pay_profiles') ? DB::table('staff_pay_profiles')->where('staff_id', $s->id)->first() : null;
            $bank = $pp->bank_name ?? ($s->bank_name ?? null);
            $last4 = $pp->account_last4 ?? (!empty($s->account_number) ? substr((string) $s->account_number, -4) : null);
            if ($bank || $last4) {
                $sections[] = $this->section('Salary account', [
                    'Bank'         => $bank,
                    'Account name' => $pp->account_name ?? ($s->account_name ?? null),
                    'Account no.'  => $last4 ? '••••••' . $last4 : null,
                ]);
            }
        }

        return $this->ok([
            'name'       => $user->name,
            'avatar_url' => $this->safe(fn () => $user->avatar_url),
            'headline'   => $s ? trim(implode(' · ', array_filter([$s->job_title ?? null, $s->department ?? null]))) ?: ($roles[0] ?? null) : ($roles[0] ?? null),
            'is_staff'   => (bool) $s,
            'roles'      => $roles,
            'sections'   => array_values(array_filter($sections, fn ($sec) => !empty($sec['items']))),
        ]);
    }

    /** Builds a section, leaving out empty values. */
    protected function section(string $title, array $pairs): array
    {
        $items = [];
        foreach ($pairs as $label => $value) {
            if ($value === null || $value === '' || $value === '-') continue;
            $items[] = ['label' => $label, 'value' => (string) $value];
        }
        return ['title' => $title, 'items' => $items];
    }

    protected function date($v): ?string
    {
        if (!$v) return null;
        try { return Carbon::parse($v)->format('j M Y'); } catch (\Throwable $e) { return (string) $v; }
    }

    protected function years($v): ?string
    {
        if (!$v) return null;
        try {
            $d = Carbon::parse($v);
            if ($d->isFuture()) return null;
            $y = $d->diffInYears(now());
            $m = $d->copy()->addYears($y)->diffInMonths(now());
            return $y ? $y . ' yr' . ($y > 1 ? 's' : '') . ($m ? ' ' . $m . ' mo' : '') : $m . ' mo';
        } catch (\Throwable $e) { return null; }
    }

    protected function pretty($v): ?string
    {
        return $v ? ucwords(str_replace('_', ' ', (string) $v)) : null;
    }

    protected function realEmail(?string $email): ?string
    {
        return $email && !str_contains($email, '@parents.') ? $email : null;
    }

    protected function safe(callable $fn, $default = null)
    {
        try { return $fn(); } catch (\Throwable $e) { return $default; }
    }
}

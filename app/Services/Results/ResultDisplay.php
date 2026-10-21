<?php

namespace App\Services\Results;

use App\Models\ResultDisplaySetting;
use App\Services\ClassResultsLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What parents and students see on result pages — portal and mobile app.
 *
 * The admin turns each item on or off under Admin → Result display. The
 * printed report card keeps its own layout and column picker.
 *
 *   ResultDisplay::settings()                 → merged switches
 *   ResultDisplay::summary($stu,$term,$ses,$class) → totals, averages, positions, GPA
 *   ResultDisplay::apply($result)             → strips what the school hides, adds `display`
 */
class ResultDisplay
{
    /** Per-subject items: key => [label, default, hint]. */
    public const SUBJECT = [
        'assessments'      => ['CA & exam breakdown', true, 'Each continuous assessment and the exam score.'],
        'total'            => ['Term total', true, 'The subject total for the term.'],
        'bf'               => ['Brought forward (BF)', false, 'Last term\'s cumulative carried into this term.'],
        'cum'              => ['Cumulative (Cum)', false, 'Running cumulative score.'],
        'cum_average'      => ['Cumulative average', true, 'Cumulative average for the subject.'],
        'grade'            => ['Grade', true, ''],
        'remark'           => ['Remark', true, 'e.g. Excellent, Very good.'],
        'position'         => ['Subject position — class (by cumulative)', true, 'Rank among all arms of the class.'],
        'position_total'   => ['Subject position — class (by term total)', false, ''],
        'arm_position'     => ['Subject position — arm (by term total)', false, 'Rank within the student\'s own arm.'],
        'arm_position_cum' => ['Subject position — arm (by cumulative)', false, ''],
        'class_average'    => ['Subject class average', true, ''],
        'highest_lowest'   => ['Highest & lowest score in class', false, ''],
    ];

    /** Whole-result items: key => [label, default, hint]. */
    public const SUMMARY = [
        'total_score'    => ['Total obtained', true, 'Sum of all subject totals.'],
        'average'        => ['Term average', true, 'Average of the subject totals.'],
        'cum_overall'    => ['Cumulative average (all subjects)', true, ''],
        'class_position' => ['Overall position in class', true, 'Among all arms of the class (e.g. all JSS 1 arms).'],
        'arm_position'   => ['Overall position in arm', false, 'Within the student\'s own arm (e.g. JSS 1 A).'],
        'class_size'     => ['Number in class / arm', true, 'Shown next to the positions ("5th of 42").'],
        'gpa'            => ['GPA', true, 'Grade point average.'],
        'comments'       => ['Teacher & principal comments', true, ''],
        'attendance'     => ['Times absent', true, ''],
        'report_card'    => ['Report card (PDF) download', true, ''],
    ];

    protected static ?array $cache = null;

    /** 'dynamic' (assessments per class category) or 'fixed' (CA1, CA2, CA3, Exam). */
    public static function scoreModel(): string
    {
        return config('services.mobile.score_model', 'dynamic') === 'fixed' ? 'fixed' : 'dynamic';
    }

    public static function supportsGpa(): bool
    {
        return self::scoreModel() === 'dynamic';
    }

    /** Field catalogue for the settings page, without items this portal does not have. */
    public static function catalogue(): array
    {
        $summary = self::SUMMARY;
        if (!self::supportsGpa()) unset($summary['gpa']);
        return ['subject' => self::SUBJECT, 'summary' => $summary];
    }

    public static function row(): ?ResultDisplaySetting
    {
        try {
            return Schema::hasTable('result_display_settings') ? ResultDisplaySetting::find(1) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Merged settings: ['parents_can_view','students_can_view','position_basis','subject'=>[..],'summary'=>[..]]. */
    public static function settings(): array
    {
        if (self::$cache !== null) return self::$cache;

        $row = self::row();
        $saved = (array) ($row->fields ?? []);
        $out = [
            'parents_can_view'  => $row ? (bool) $row->parents_can_view : (bool) config('services.mobile.parent_results', true),
            'students_can_view' => $row ? (bool) $row->students_can_view : (bool) config('services.mobile.parent_results', true),
            'position_basis'    => ($row->position_basis ?? 'cum') === 'term' ? 'term' : 'cum',
            'subject' => [], 'summary' => [],
        ];
        foreach (self::catalogue() as $group => $items) {
            foreach ($items as $key => [$label, $default]) {
                $out[$group][$key] = array_key_exists("$group.$key", $saved) ? (bool) $saved["$group.$key"] : $default;
            }
        }
        return self::$cache = $out;
    }

    public static function save(array $data, ?int $userId = null): void
    {
        $fields = [];
        foreach (self::catalogue() as $group => $items) {
            foreach (array_keys($items) as $key) $fields["$group.$key"] = !empty($data[$group][$key]);
        }
        ResultDisplaySetting::updateOrCreate(['id' => 1], [
            'parents_can_view'  => !empty($data['parents_can_view']),
            'students_can_view' => !empty($data['students_can_view']),
            'position_basis'    => ($data['position_basis'] ?? 'cum') === 'term' ? 'term' : 'cum',
            'fields'            => $fields,
            'updated_by'        => $userId,
        ]);
        self::$cache = null;
        Cache::forget('result-display.v1');
    }

    public static function show(string $group, string $key): bool
    {
        return (bool) (self::settings()[$group][$key] ?? false);
    }

    /**
     * Whether this viewer may see results at all. Staff (anyone who is not
     * only a parent/student) always may; the hold for unpaid fees is
     * checked separately by the result-access service.
     */
    public static function viewerMaySee($user, int $studentId): bool
    {
        if (!$user) return false;
        $s = self::settings();
        if ((int) ($user->student_id ?? 0) === $studentId) return $s['students_can_view'];
        if (class_exists(\App\Services\Parents\ParentAccountService::class)
            && \App\Services\Parents\ParentAccountService::isParentOf($user, $studentId)) {
            return $s['parents_can_view'];
        }
        return true; // staff looking a student up
    }

    /** Ordinal: 1st, 2nd, 3rd, 11th, 22nd. */
    public static function ordinal($n): ?string
    {
        if ($n === null || $n === '' || (int) $n <= 0) return null;
        $n = (int) $n;
        $suffix = in_array($n % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');
        return $n . $suffix;
    }

    /**
     * Totals, averages, overall positions and GPA for one student's term.
     * Positions rank every student with results in the class (all arms) and
     * in the student's arm, on the school's basis (cumulative or term average);
     * ties share a position (1, 2, 2, 4) like the broadsheet.
     */
    public static function summary(int $studentId, int $termId, int $sessionId, int $classId): array
    {
        $basis = self::settings()['position_basis'];
        $board = Cache::remember("result-summary.v1.$classId.$termId.$sessionId.$basis", now()->addMinutes(10),
            fn () => self::board($termId, $sessionId, $classId, $basis));

        $me = $board['students'][$studentId] ?? null;

        return [
            'basis'          => $basis,
            'term_total'     => $me['total_term'] ?? null,
            'term_average'   => $me['term_ave'] ?? null,
            'cum_average'    => $me['cum_ave'] ?? null,
            'gpa'            => self::supportsGpa() ? ($me['gpa'] ?? null) : null,
            'class_position' => $me['class_position'] ?? null,
            'class_size'     => $board['class_size'],
            'arm_position'   => $me['arm_position'] ?? null,
            'arm_size'       => $board['arm_sizes'][$me['arm_id'] ?? $classId] ?? null,
            'class_name'     => $board['class_name'],
        ];
    }

    protected static function board(int $termId, int $sessionId, int $classId, string $basis): array
    {
        $name = DB::table('schoolclass')->where('id', $classId)->value('schoolclass');
        $ids = $name !== null
            ? DB::table('schoolclass')->where('schoolclass', $name)->pluck('id')->map(fn ($v) => (int) $v)->all()
            : [$classId];

        try {
            $loader = app(ClassResultsLoader::class);
            // CSS Kabba's loader takes the GPA basis as its fourth argument; TopClass's does not need it.
            $data = self::supportsGpa()
                ? $loader->load($termId, $sessionId, $ids, $basis === 'term' ? 'total' : 'cum_ave')
                : $loader->load($termId, $sessionId, $ids);
        } catch (\Throwable $e) {
            report($e);
            return ['students' => [], 'class_size' => null, 'arm_sizes' => [], 'class_name' => $name];
        }

        $measure = $basis === 'term' ? 'term_ave' : 'cum_ave';
        $rows = collect($data['rows'] ?? [])->filter(fn ($r) => ($r['num_subjects'] ?? 0) > 0)->values();

        $rank = function ($set) use ($measure) {
            $out = []; $prev = null; $pos = 0;
            foreach ($set->sortByDesc($measure)->values() as $i => $r) {
                if ($prev === null || $r[$measure] < $prev) { $pos = $i + 1; $prev = $r[$measure]; }
                $out[$r['id']] = $pos;
            }
            return $out;
        };

        $classPos = $rank($rows);
        $armPos = []; $armSizes = [];
        foreach ($rows->groupBy('schoolclassid') as $armId => $set) {
            $armPos += $rank($set);
            $armSizes[(int) $armId] = $set->count();
        }

        $students = [];
        foreach ($rows as $r) {
            $students[$r['id']] = [
                'total_term'     => isset($r['total_term']) ? round((float) $r['total_term'], 1) : null,
                'term_ave'       => isset($r['term_ave']) ? round((float) $r['term_ave'], 1) : null,
                'cum_ave'        => isset($r['cum_ave']) ? round((float) $r['cum_ave'], 1) : null,
                'gpa'            => isset($r['gpa']) ? round((float) $r['gpa'], 2) : null,
                'class_position' => $classPos[$r['id']] ?? null,
                'arm_position'   => $armPos[$r['id']] ?? null,
                'arm_id'         => (int) $r['schoolclassid'],
            ];
        }

        return ['students' => $students, 'class_size' => $rows->count(), 'arm_sizes' => $armSizes, 'class_name' => $name];
    }

    /**
     * Applies the switches to an API result (StudentController::termDetail):
     * removes hidden subject keys and summary items, and adds `display` so
     * the app knows what to draw.
     */
    public static function apply(array $result): array
    {
        $s = self::settings();
        $sub = $s['subject'];
        $sum = $s['summary'];

        $subjectKeys = [
            'assessments' => ['assessments'], 'total' => ['total'], 'bf' => ['bf'], 'cum' => ['cum'],
            'cum_average' => ['cum_average'], 'grade' => ['grade'], 'remark' => ['remark'],
            'position' => ['position', 'position_label'], 'position_total' => ['position_total'],
            'arm_position' => ['arm_position'], 'arm_position_cum' => ['arm_position_cum'],
            'class_average' => ['class_average'], 'highest_lowest' => ['highest', 'lowest'],
        ];
        $result['subjects'] = collect($result['subjects'] ?? [])->map(function ($row) use ($sub, $subjectKeys) {
            $row = (array) $row;
            foreach ($subjectKeys as $flag => $keys) {
                if (empty($sub[$flag])) foreach ($keys as $k) unset($row[$k]);
            }
            return $row;
        })->values()->all();

        if (empty($sum['total_score'])) unset($result['total_score']);
        if (empty($sum['average'])) unset($result['average']);
        if (empty($sum['comments'])) { unset($result['class_teacher_comment'], $result['principal_comment']); }
        if (empty($sum['attendance'])) unset($result['times_absent']);
        if (empty($sum['report_card'])) unset($result['report_card_url']);

        if (isset($result['summary']) && is_array($result['summary'])) {
            $m = &$result['summary'];
            if (empty($sum['total_score'])) unset($m['term_total']);
            if (empty($sum['average'])) unset($m['term_average']);
            if (empty($sum['cum_overall'])) unset($m['cum_average']);
            if (empty($sum['gpa'])) unset($m['gpa']);
            if (empty($sum['class_position'])) unset($m['class_position'], $m['class_position_label']);
            if (empty($sum['arm_position'])) unset($m['arm_position'], $m['arm_position_label']);
            if (empty($sum['class_size'])) unset($m['class_size'], $m['arm_size']);
            unset($m);
        }

        $result['display'] = [
            'subject' => $sub,
            'summary' => $sum,
            'position_basis' => $s['position_basis'],
            'score_model' => self::scoreModel(),
        ];
        return $result;
    }
}

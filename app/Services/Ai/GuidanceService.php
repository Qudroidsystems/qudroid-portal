<?php

namespace App\Services\Ai;

use App\Models\AiGuidanceSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Learning & career guidance for one student, from their results.
 *
 * 1. A profile is built from the school's own records: subject scores across
 *    terms (only terms whose results the family may see), attendance, the
 *    teacher's affective / psychomotor ratings and e-learning activity.
 *    No name, photo or contact detail is ever included.
 * 2. Built-in rules turn that into a complete report (always available).
 * 3. If the admin chose an AI provider and gave a key, the AI writes a richer,
 *    more personal report from the same profile; if that fails for any reason
 *    the rules report is used, so the app never shows an error for this.
 *
 * Reports are stored per student and term and only rebuilt when the results
 * change or the user asks (limited per term by the admin).
 */
class GuidanceService
{
    /** Subject families used by the rules (lower-case keywords → cluster). */
    protected const CLUSTERS = [
        'sciences'   => ['math', 'physics', 'chemistry', 'biology', 'basic science', 'further', 'agric', 'computer', 'ict', 'data processing', 'health'],
        'technology' => ['technical', 'basic tech', 'technology', 'drawing', 'electr', 'metal', 'wood', 'building', 'auto'],
        'language'   => ['english', 'literature', 'french', 'yoruba', 'igbo', 'hausa', 'arabic', 'language', 'oral'],
        'humanities' => ['history', 'government', 'civic', 'social', 'crs', 'irs', 'christian', 'islamic', 'religio', 'geography', 'security'],
        'business'   => ['economics', 'commerce', 'account', 'business', 'marketing', 'book', 'office', 'store', 'insurance'],
        'creative'   => ['art', 'music', 'creative', 'cultural', 'home econ', 'food', 'cloth', 'garment', 'fashion', 'catering', 'physical', 'pe'],
    ];

    protected const DISCIPLINES = [
        'sciences'   => [['Medicine & Surgery', ['Biology', 'Chemistry', 'Physics', 'English']], ['Pharmacy', ['Chemistry', 'Biology', 'Mathematics']],
                         ['Nursing / Medical Laboratory Science', ['Biology', 'Chemistry']], ['Computer Science', ['Mathematics', 'Physics']],
                         ['Engineering (Civil, Mechanical, Electrical)', ['Mathematics', 'Physics', 'Chemistry']], ['Agricultural Science / Food Science', ['Biology', 'Chemistry', 'Agriculture']]],
        'technology' => [['Architecture', ['Mathematics', 'Physics', 'Technical Drawing']], ['Building Technology / Quantity Surveying', ['Mathematics', 'Physics']],
                         ['Electrical / Electronics Engineering', ['Mathematics', 'Physics']]],
        'language'   => [['Mass Communication / Journalism', ['English', 'Literature']], ['English & Literary Studies', ['English', 'Literature']],
                         ['Linguistics & Languages', ['English', 'a Nigerian or foreign language']]],
        'humanities' => [['Law', ['English', 'Literature', 'Government']], ['Political Science / International Relations', ['Government', 'History']],
                         ['Sociology / Psychology', ['English', 'Government', 'Biology']], ['Geography & Urban Planning', ['Geography', 'Mathematics']]],
        'business'   => [['Accounting', ['Mathematics', 'Economics', 'Accounting']], ['Economics', ['Mathematics', 'Economics']],
                         ['Business Administration / Banking & Finance', ['Mathematics', 'Economics', 'Commerce']]],
        'creative'   => [['Fine & Applied Arts / Design', ['Fine Art', 'English']], ['Music / Theatre Arts', ['Music', 'Literature']],
                         ['Hospitality & Tourism / Fashion Design', ['Home Economics', 'English']]],
    ];

    protected const CLUSTER_LABELS = [
        'sciences' => 'Sciences & Mathematics', 'technology' => 'Technology & Technical work', 'language' => 'Language & Communication',
        'humanities' => 'Social Sciences & Humanities', 'business' => 'Business & Commerce', 'creative' => 'Creative & Practical Arts',
    ];

    /**
     * The guidance for a student (stored copy if still current).
     * @param array<int,array> $allowedTerms terms the viewer may see: [['session_id','term_id','label'], ...] oldest first
     */
    public function forStudent(int $studentId, array $allowedTerms, bool $refresh = false, ?int $userId = null): array
    {
        $settings = AiGuidanceSetting::current();
        $profile = $this->profile($studentId, $allowedTerms);
        if (!$profile['subjects']) {
            return ['available' => false, 'reason' => 'no_results', 'message' => 'Guidance appears once at least one term\'s results are published.'];
        }

        $latest = end($allowedTerms) ?: [];
        $hash = hash('sha256', json_encode([$profile, $settings->provider, $settings->modelName()]));
        $stored = Schema::hasTable('ai_guidance_reports')
            ? DB::table('ai_guidance_reports')->where('student_id', $studentId)
                ->where('session_id', $latest['session_id'] ?? null)->where('term_id', $latest['term_id'] ?? null)
                ->orderByDesc('id')->first()
            : null;

        $used = $stored ? DB::table('ai_guidance_reports')->where('student_id', $studentId)
            ->where('session_id', $latest['session_id'] ?? null)->where('term_id', $latest['term_id'] ?? null)->count() : 0;
        $limit = max(1, (int) $settings->refreshes_per_term);

        // Reuse the stored report unless the results changed or a refresh was asked
        // for (and the term's refresh allowance is not used up).
        if ($stored && (($stored->input_hash === $hash && !$refresh) || ($refresh && $used >= $limit))) {
            return $this->wrap(json_decode($stored->content, true) ?: [], $stored->provider, $stored->created_at, max(0, $limit - max(0, $used - 1)), $profile);
        }

        $report = $this->rules($profile);
        $provider = 'rules';
        if ($settings->usesAi()) {
            try {
                $ai = $this->askAi($settings, $profile, $report);
                if ($ai) {
                    $report = array_merge($report, array_filter($ai, fn ($v) => $v !== null && $v !== [] && $v !== ''));
                    $provider = $settings->provider;
                }
            } catch (\Throwable $e) {
                Log::warning('AI guidance failed; using built-in rules', ['provider' => $settings->provider, 'error' => $e->getMessage()]);
            }
        }

        if (Schema::hasTable('ai_guidance_reports')) {
            DB::table('ai_guidance_reports')->insert([
                'student_id' => $studentId, 'session_id' => $latest['session_id'] ?? null, 'term_id' => $latest['term_id'] ?? null,
                'provider' => $provider, 'model' => $provider === 'rules' ? null : $settings->modelName(),
                'input_hash' => $hash, 'content' => json_encode($report), 'requested_by' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $used++;
        }

        return $this->wrap($report, $provider, now(), max(0, $limit - max(0, $used - 1)), $profile);
    }

    protected function wrap(array $report, string $provider, $at, int $refreshesLeft, array $profile): array
    {
        return [
            'available'      => true,
            'generated_at'   => \Carbon\Carbon::parse($at)->toIso8601String(),
            'source'         => $provider === 'rules' ? 'rules' : 'ai',
            'provider_label' => AiGuidanceSetting::PROVIDERS[$provider][0] ?? $provider,
            'refreshes_left' => $refreshesLeft,
            'based_on'       => [
                'terms'    => count($profile['terms']),
                'subjects' => count($profile['subjects']),
                'latest'   => end($profile['terms'])['label'] ?? null,
            ],
            'report'         => $report,
        ];
    }

    // ── profile ─────────────────────────────────────────────────────────

    public function profile(int $studentId, array $allowedTerms): array
    {
        $allowed = collect($allowedTerms)->map(fn ($t) => $t['session_id'] . '-' . $t['term_id'])->flip();

        $rows = DB::table('broadsheet_records as br')->join('broadsheets as b', 'b.broadsheet_record_id', '=', 'br.id')
            ->join('subject as sj', 'sj.id', '=', 'br.subject_id')
            ->leftJoin('schoolclass as c', 'c.id', '=', 'br.schoolclass_id')
            ->where('br.student_id', $studentId)->where('b.total', '>', 0)
            ->orderBy('br.session_id')->orderBy('b.term_id')
            ->get(['br.session_id', 'b.term_id', 'sj.subject', 'b.total', 'b.avg', 'c.schoolclass'])
            ->filter(fn ($r) => isset($allowed[$r->session_id . '-' . $r->term_id]));

        $terms = [];
        foreach ($allowedTerms as $t) {
            $g = $rows->filter(fn ($r) => $r->session_id == $t['session_id'] && $r->term_id == $t['term_id']);
            if ($g->isEmpty()) continue;
            $terms[] = ['label' => $t['label'] ?? '', 'class' => $g->first()->schoolclass, 'average' => round($g->avg('total'), 1),
                        'class_average' => $g->whereNotNull('avg')->count() ? round($g->avg('avg'), 1) : null];
        }

        $subjects = [];
        foreach ($rows->groupBy('subject') as $name => $g) {
            $scores = $g->pluck('total')->map(fn ($v) => round((float) $v, 1))->values()->all();
            $subjects[] = [
                'subject' => $name, 'latest' => end($scores), 'average' => round(array_sum($scores) / count($scores), 1),
                'trend' => count($scores) > 1 ? round(end($scores) - $scores[count($scores) - 2], 1) : null,
                'class_average' => $g->last()->avg !== null ? round((float) $g->last()->avg, 1) : null,
                'terms' => count($scores),
            ];
        }
        usort($subjects, fn ($a, $b) => $b['average'] <=> $a['average']);

        $classLevel = $terms ? end($terms)['class'] : null;

        $traits = [];
        if (Schema::hasTable('studentpersonalityprofiles')) {
            $p = DB::table('studentpersonalityprofiles')->where('studentid', $studentId)->orderByDesc('sessionid')->orderByDesc('termid')->first();
            if ($p) {
                foreach (['punctuality', 'neatness', 'leadership', 'attitude', 'honesty', 'cooperation', 'selfcontrol', 'politeness', 'stability',
                          'gamesandsports', 'attentiveness_in_class', 'class_participation', 'relationship_with_others', 'doing_assignment',
                          'writing_skill', 'reading_skill', 'spoken_english_communication', 'hand_writing', 'club', 'music'] as $k) {
                    $v = $p->{$k} ?? null;
                    if ($v !== null && trim((string) $v) !== '') $traits[str_replace('_', ' ', $k)] = trim((string) $v);
                }
            }
        }

        $att = DB::table('student_attendance')->where('student_id', $studentId)
            ->selectRaw("SUM(status IN ('present','late')) as p, COUNT(*) as n")->first();

        $lms = null;
        if (Schema::hasTable('lms_assignment_submissions')) {
            $graded = DB::table('lms_assignment_submissions as s')->join('lms_assignments as a', 'a.id', '=', 's.assignment_id')
                ->where('s.student_id', $studentId)->whereNotNull('s.score')->get(['s.score', 'a.max_score']);
            if ($graded->isNotEmpty()) {
                $lms = ['assignments_marked' => $graded->count(),
                        'average_percent' => round($graded->map(fn ($r) => $r->max_score > 0 ? $r->score / $r->max_score * 100 : 0)->avg(), 1)];
            }
        }

        return [
            'class_level'     => $classLevel,
            'senior'          => $classLevel ? (bool) preg_match('/\b(ss|sss|senior)/i', $classLevel) : false,
            'terms'           => $terms,
            'subjects'        => $subjects,
            'attendance_rate' => $att && $att->n ? round($att->p / $att->n * 100) : null,
            'teacher_ratings' => $traits,
            'elearning'       => $lms,
        ];
    }

    // ── built-in rules ──────────────────────────────────────────────────

    protected function cluster(string $subject): ?string
    {
        $s = strtolower($subject);
        foreach (self::CLUSTERS as $k => $words) {
            foreach ($words as $w) {
                if (str_contains($s, $w)) return $k;
            }
        }
        return null;
    }

    public function rules(array $p): array
    {
        $subjects = collect($p['subjects']);
        $byCluster = $subjects->groupBy(fn ($s) => $this->cluster($s['subject']) ?? 'other')
            ->map(fn (Collection $g) => round($g->avg('average'), 1))->forget('other')->sortDesc();
        $top = $byCluster->keys()->take(2)->values();
        $strong = $subjects->filter(fn ($s) => $s['average'] >= 65)->take(4);
        $weak = $subjects->sortBy('latest')->filter(fn ($s) => $s['latest'] < 55)->take(3);
        $rising = $subjects->filter(fn ($s) => ($s['trend'] ?? 0) >= 5)->pluck('subject');
        $falling = $subjects->filter(fn ($s) => ($s['trend'] ?? 0) <= -5)->pluck('subject');
        $overall = $subjects->avg('average');

        $band = $overall >= 70 ? 'excellent' : ($overall >= 58 ? 'good' : ($overall >= 45 ? 'fair' : 'needs support'));
        $topLabel = $top->map(fn ($k) => self::CLUSTER_LABELS[$k] ?? $k)->implode(' and ');

        $summary = 'Overall performance is ' . $band . ' (average ' . round($overall) . '%).'
            . ($topLabel ? ' The strongest area is ' . $topLabel . '.' : '')
            . ($rising->isNotEmpty() ? ' Improving in ' . $rising->take(3)->implode(', ') . '.' : '')
            . ($falling->isNotEmpty() ? ' Watch ' . $falling->take(3)->implode(', ') . ', which dropped since last term.' : '');

        $readingPlan = [];
        foreach ($weak as $w) {
            $readingPlan[] = ['focus' => $w['subject'], 'what' => 'Re-read the class notes and one textbook chapter per topic; do 10 past-question items per session.',
                              'how_often' => '3 × 40 minutes a week', 'why' => 'Latest score ' . $w['latest'] . '% — the quickest gain.'];
        }
        foreach ($strong->take(2) as $s) {
            $readingPlan[] = ['focus' => $s['subject'] . ' (stretch)', 'what' => 'Read beyond the syllabus: one enrichment book, article or video series and try competition questions.',
                              'how_often' => '1 × 45 minutes a week', 'why' => 'Already strong (' . $s['average'] . '%) — build depth.'];
        }
        $readingPlan[] = ['focus' => 'General reading', 'what' => 'An age-appropriate novel or non-fiction book; summarise each chapter in 3 sentences.',
                          'how_often' => '20 minutes daily', 'why' => 'Reading every day lifts comprehension in every subject.'];

        $ratings = collect($p['teacher_ratings'] ?? []);
        $good = $ratings->filter(fn ($v) => preg_match('/^(a|5|excellent|very good)/i', (string) $v))->keys();
        $style = $byCluster->keys()->first() === 'sciences' ? 'Logical / problem-solving' : ($byCluster->keys()->first() === 'language' || $byCluster->keys()->first() === 'humanities'
            ? 'Verbal / reflective' : ($byCluster->keys()->first() === 'creative' ? 'Hands-on / visual' : 'Balanced'));

        $disciplines = [];
        foreach ($top as $i => $k) {
            foreach (self::DISCIPLINES[$k] ?? [] as $j => [$name, $keySubjects]) {
                if (count($disciplines) >= 5) break 2;
                $disciplines[] = ['discipline' => $name, 'fit' => max(40, min(95, (int) round(($byCluster[$k] ?? 50) + 10 - $i * 8 - $j * 3))),
                                  'why' => 'Matches strength in ' . (self::CLUSTER_LABELS[$k] ?? $k) . '.', 'key_subjects' => $keySubjects];
            }
        }

        $vocations = $top->map(fn ($k) => [
            'sciences' => ['field' => 'Health, science & engineering', 'why' => 'Enjoys and does well in numbers and experiments.'],
            'technology' => ['field' => 'Technology, design & construction', 'why' => 'Strong in technical and practical subjects.'],
            'language' => ['field' => 'Media, writing & communication', 'why' => 'Expresses ideas well in language subjects.'],
            'humanities' => ['field' => 'Law, public service & social work', 'why' => 'Does well understanding people and society.'],
            'business' => ['field' => 'Business, finance & entrepreneurship', 'why' => 'Strong in economics and commercial subjects.'],
            'creative' => ['field' => 'Creative arts, design & hospitality', 'why' => 'Shows talent in creative and practical work.'],
        ][$k] ?? null)->filter()->values()->all();

        return [
            'summary'         => $summary,
            'strengths'       => $strong->map(fn ($s) => $s['subject'] . ' (' . $s['average'] . '%)')->values()->all(),
            'areas_to_improve'=> $weak->map(fn ($s) => $s['subject'] . ' (' . $s['latest'] . '%)')->values()->all(),
            'learning_model'  => [
                'style' => $style,
                'description' => 'Based on where the student does best, they are likely to learn most easily through ' . strtolower($style) . ' approaches.',
                'tips' => ['Study in 40-minute blocks with 10-minute breaks.', 'Teach a topic back to someone after learning it.',
                           'Practise past questions under timed conditions every week.', 'Keep a mistakes notebook and review it on Sundays.'],
            ],
            'study_plan'      => [
                ['period' => 'Weeks 1–4', 'focus' => 'Close gaps', 'actions' => array_values(array_filter([$weak->first()['subject'] ?? null ? 'Daily practice in ' . $weak->first()['subject'] : null, 'Finish all assignments on time', 'Ask teachers about two hard topics each week']))],
                ['period' => 'Weeks 5–8', 'focus' => 'Build consistency', 'actions' => ['Weekly self-tests in every core subject', 'Join a study group or club linked to a strong subject']],
                ['period' => 'Before exams', 'focus' => 'Revise & practise', 'actions' => ['Past questions for every subject', 'Sleep well and keep a revision timetable']],
            ],
            'reading_plan'    => $readingPlan,
            'cognitive_profile'=> array_values(array_filter([
                $byCluster->has('sciences') ? ['trait' => 'Numerical & logical reasoning', 'level' => $this->level($byCluster['sciences']), 'evidence' => 'Science/maths average ' . $byCluster['sciences'] . '%'] : null,
                $byCluster->has('language') ? ['trait' => 'Verbal & reading ability', 'level' => $this->level($byCluster['language']), 'evidence' => 'Language average ' . $byCluster['language'] . '%'] : null,
                $byCluster->has('humanities') ? ['trait' => 'Social understanding & recall', 'level' => $this->level($byCluster['humanities']), 'evidence' => 'Humanities average ' . $byCluster['humanities'] . '%'] : null,
                $p['attendance_rate'] !== null ? ['trait' => 'Consistency', 'level' => $p['attendance_rate'] >= 90 ? 'High' : ($p['attendance_rate'] >= 75 ? 'Moderate' : 'Needs work'), 'evidence' => 'Attendance ' . $p['attendance_rate'] . '%'] : null,
            ])),
            'passions'        => array_values(array_unique(array_merge(
                $strong->pluck('subject')->take(3)->all(),
                $good->filter(fn ($k) => in_array($k, ['gamesandsports', 'music', 'club', 'leadership']))->map(fn ($k) => ucfirst($k === 'gamesandsports' ? 'sports' : $k))->all()
            ))),
            'vocational_interests' => $vocations,
            'higher_education'=> $disciplines,
            'parent_tips'     => ['Check the timetable together each Sunday and agree study times.', 'Praise effort and improvement, not only grades.',
                                  'Speak with the class teacher about ' . ($weak->first()['subject'] ?? 'any subject that drops') . '.', 'Limit screen time on school nights.'],
            'disclaimer'      => 'This guidance is generated from school records to support — not replace — conversations with teachers, counsellors and the student.',
        ];
    }

    protected function level(float $v): string
    {
        return $v >= 70 ? 'High' : ($v >= 55 ? 'Moderate' : 'Developing');
    }

    // ── external AI ─────────────────────────────────────────────────────

    protected function askAi(AiGuidanceSetting $s, array $profile, array $baseline): ?array
    {
        $schema = '{"summary":string,"strengths":[string],"areas_to_improve":[string],'
            . '"learning_model":{"style":string,"description":string,"tips":[string]},'
            . '"study_plan":[{"period":string,"focus":string,"actions":[string]}],'
            . '"reading_plan":[{"focus":string,"what":string,"how_often":string,"why":string}],'
            . '"cognitive_profile":[{"trait":string,"level":"High|Moderate|Developing","evidence":string}],'
            . '"passions":[string],"vocational_interests":[{"field":string,"why":string}],'
            . '"higher_education":[{"discipline":string,"fit":0-100,"why":string,"key_subjects":[string]}],'
            . '"parent_tips":[string],"disclaimer":string}';

        $system = 'You are an experienced school guidance counsellor. You write warm, specific, practical guidance for a student and their parents, '
            . 'based only on the school records provided. Never invent facts or diagnose conditions; describe tendencies, not fixed labels. '
            . 'Recommend real, widely available reading where useful. Be encouraging and concrete. '
            . 'School context: ' . ($s->school_context ?: 'secondary school') . ' '
            . 'Reply with ONLY a JSON object matching this shape (no markdown): ' . $schema;

        $user = "Student record (anonymous):\n" . json_encode($profile, JSON_PRETTY_PRINT)
            . "\n\nA simple rule-based draft you may improve on:\n" . json_encode($baseline)
            . "\n\nWrite the guidance JSON now. Keep each list to 3–6 items; 'higher_education' 3–6 disciplines with honest fit scores.";

        $model = $s->modelName();
        $text = match ($s->provider) {
            'anthropic' => $this->anthropic($s->api_key, $model, $system, $user),
            'openai'    => $this->openai($s->api_key, $model, $system, $user),
            'gemini'    => $this->gemini($s->api_key, $model, $system, $user),
            default     => null,
        };
        if (!$text) return null;

        $json = trim($text);
        if (preg_match('/\{.*\}/s', $json, $m)) $json = $m[0];
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    protected function anthropic(string $key, string $model, string $system, string $user): ?string
    {
        $res = Http::timeout(60)->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model, 'max_tokens' => 3000, 'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
            ])->throw();
        return collect($res->json('content', []))->where('type', 'text')->pluck('text')->implode('');
    }

    protected function openai(string $key, string $model, string $system, string $user): ?string
    {
        $res = Http::timeout(60)->withToken($key)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model, 'response_format' => ['type' => 'json_object'],
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
        ])->throw();
        return $res->json('choices.0.message.content');
    }

    protected function gemini(string $key, string $model, string $system, string $user): ?string
    {
        $res = Http::timeout(60)->withHeaders(['x-goog-api-key' => $key])
            ->post('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent', [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                'generationConfig' => ['responseMimeType' => 'application/json'],
            ])->throw();
        return $res->json('candidates.0.content.parts.0.text');
    }

    /** Sends a tiny request to check the key and model work. Returns null on success, or the error. */
    public function test(AiGuidanceSetting $s): ?string
    {
        try {
            $text = match ($s->provider) {
                'anthropic' => $this->anthropic($s->api_key, $s->modelName(), 'Reply with the JSON {"ok":true}.', 'ping'),
                'openai'    => $this->openai($s->api_key, $s->modelName(), 'Reply with the JSON {"ok":true}.', 'ping'),
                'gemini'    => $this->gemini($s->api_key, $s->modelName(), 'Reply with the JSON {"ok":true}.', 'ping'),
                default     => 'ok',
            };
            return $text ? null : 'The AI returned an empty reply.';
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }
}

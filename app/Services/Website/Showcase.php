<?php

namespace App\Services\Website;

use App\Models\Academy\Certificate;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\User;
use App\Models\Website\StudentProfile;
use App\Models\Website\StudentProject;
use App\Models\Website\Testimonial;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The student showcase and testimonials.
 *
 *  - A student fills in their public profile and projects in the portal and asks
 *    for it to be shown; staff publish it (or hide it). Their agreement is
 *    recorded; without it nothing about them is public. Turning sharing off
 *    takes the page down at once.
 *  - Students write testimonials in the portal; clients through a link staff
 *    send them. Both wait for staff to publish them.
 */
class Showcase
{
    public const MAX_PROJECTS = 12;

    public function profileFor(Student $student): StudentProfile
    {
        return $student->profile ?? new StudentProfile([
            'student_id' => $student->id, 'status' => 'draft', 'skills' => [], 'links' => [],
        ]);
    }

    /** Validation rules for the profile form (student and staff). */
    public static function profileRules(): array
    {
        return [
            'headline' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1500'],
            'location' => ['nullable', 'string', 'max:80'],
            'skills' => ['nullable', 'string', 'max:400'],
            'links' => ['nullable', 'array'],
            'links.*' => ['nullable', 'string', 'max:300'],
            'open_to_work' => ['nullable', 'boolean'],
            'photo_file' => ['nullable', 'image', 'max:4096'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Save the profile fields. $share: the student wants it on the website (true),
     * doesn't (false), or no change (null).
     */
    public function saveProfile(StudentProfile $profile, array $data, Request $request, ?bool $share, User $by, bool $staff = false): StudentProfile
    {
        $links = [];
        foreach (array_keys(StudentProfile::LINKS) as $k) {
            $url = trim((string) ($data['links'][$k] ?? ''));
            if ($url === '') continue;
            if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
            if (!filter_var($url, FILTER_VALIDATE_URL)) throw ValidationException::withMessages(["links.$k" => 'That ' . StudentProfile::LINKS[$k][0] . ' link doesn\'t look right.']);
            $links[$k] = mb_substr($url, 0, 300);
        }

        $profile->fill([
            'headline' => trim((string) ($data['headline'] ?? '')) ?: null,
            'bio' => trim((string) ($data['bio'] ?? '')) ?: null,
            'location' => trim((string) ($data['location'] ?? '')) ?: null,
            'skills' => collect(explode(',', (string) ($data['skills'] ?? '')))->map(fn ($s) => mb_substr(trim($s), 0, 40))->filter()->unique()->take(15)->values()->all(),
            'links' => $links,
            'open_to_work' => !empty($data['open_to_work']),
        ]);
        if ($request->hasFile('photo_file')) {
            $profile->photo = $request->file('photo_file')->store('website/students', 'public');
        } elseif (!empty($data['remove_photo'])) {
            $profile->photo = null;
        }
        if (!$profile->slug) $profile->slug = $this->slug($profile);

        $wasPublic = $profile->exists && $profile->isPublic();
        if ($share === true) {
            $profile->consented_at ??= now();
            // A student's request waits for staff; staff recording agreement leave the status to them.
            if (!$staff && $profile->status === 'draft') $profile->status = 'pending';
        } elseif ($share === false) {
            // Withdrawing agreement takes the page down straight away.
            $profile->consented_at = null;
            $profile->status = 'draft';
        }
        $profile->save();

        if (!$staff && $profile->status === 'pending' && $profile->wasChanged('status')) {
            PortalNotifier::toUsers(PortalNotifier::userIdsWithPermission('Manage website'), 'Student profile to approve: ' . $profile->name(),
                'They would like their profile and projects on the website.', route('website.students.edit', $profile), 'system');
        } elseif (!$staff && $wasPublic && $profile->wasChanged(['headline', 'bio', 'photo'])) {
            PortalNotifier::toUsers(PortalNotifier::userIdsWithPermission('Manage website'), 'Student profile updated: ' . $profile->name(),
                'A published profile was changed. Have a look.', route('website.students.edit', $profile), 'system');
        }

        return $profile;
    }

    /** Staff publish, hide or feature a profile. Publishing needs the student's agreement. */
    public function review(StudentProfile $profile, string $status, User $by, ?bool $featured = null): StudentProfile
    {
        if ($status === 'published' && !$profile->consented_at) {
            throw ValidationException::withMessages(['status' => 'The student hasn\'t agreed to be on the website yet. Ask them to tick "Show my profile on the website" in My academy → My public profile, or record their agreement here.']);
        }
        $was = $profile->status;
        $profile->fill(['status' => $status, 'reviewed_by' => $by->id, 'reviewed_at' => now()] + ($featured === null ? [] : ['featured' => $featured]))->save();
        if ($status !== $was && in_array($status, ['published', 'hidden'], true)) {
            PortalNotifier::toUsers([$profile->student->user_id], $status === 'published' ? 'Your profile is on the website' : 'Your website profile is hidden',
                $status === 'published' ? 'Your profile and projects are now on our website.' : 'Staff have taken your profile off the website for now. Contact the academy if you have questions.',
                $status === 'published' ? route('site.students.show', $profile) : route('academy.my.profile'), 'course');
        }

        return $profile;
    }

    public static function projectRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'summary' => ['nullable', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:500'],
            'repo_url' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'string', 'max:300'],
            'course_id' => ['nullable', 'integer'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'image_file' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    public function saveProject(StudentProfile $profile, ?StudentProject $project, array $data, Request $request): StudentProject
    {
        if (!$project && $profile->projects()->count() >= self::MAX_PROJECTS) {
            throw ValidationException::withMessages(['title' => 'You can show up to ' . self::MAX_PROJECTS . ' projects. Remove one first.']);
        }
        $url = fn ($v, $field) => $this->url($v, $field);
        $courseIds = Enrollment::where('student_id', $profile->student_id)->pluck('course_id')->all();
        $project ??= new StudentProject(['profile_id' => $profile->id]);
        $project->fill([
            'title' => trim($data['title']),
            'summary' => trim((string) ($data['summary'] ?? '')) ?: null,
            'url' => $url($data['url'] ?? null, 'url'),
            'repo_url' => $url($data['repo_url'] ?? null, 'repo_url'),
            'tags' => collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($s) => mb_substr(trim($s), 0, 30))->filter()->unique()->take(8)->values()->all(),
            'course_id' => !empty($data['course_id']) && in_array((int) $data['course_id'], $courseIds, true) ? (int) $data['course_id'] : null,
            'position' => (int) ($data['position'] ?? $project->position ?? 0),
        ]);
        if ($request->hasFile('image_file')) {
            $project->image = $request->file('image_file')->store('website/students', 'public');
        } elseif (!empty($data['remove_image'])) {
            $project->image = null;
        }
        $project->profile_id = $profile->id;
        $project->save();

        return $project;
    }

    /** What the public page says the student studied: each course, class, and whether they finished (with the certificate). */
    public function studies(Student $student): Collection
    {
        return $student->enrollments()->with('course', 'cohort', 'certificate')->whereIn('status', ['active', 'completed'])->get()
            ->filter(fn (Enrollment $e) => $e->course && !$e->course->trashed())
            ->map(fn (Enrollment $e) => [
                'course' => $e->course, 'cohort' => $e->cohort?->name, 'year' => $e->cohort?->starts_on?->year,
                'completed' => $e->status === 'completed',
                'certificate' => $e->certificate && $e->certificate->status === 'issued' && \Illuminate\Support\Facades\Route::has('certificates.verify') && $e->certificate->verify_token
                    ? route('certificates.verify', $e->certificate->verify_token) : null,
            ])->sortByDesc('completed')->values();
    }

    // ── Testimonials ────────────────────────────────────────────────

    public static function testimonialRules(bool $client): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:120'],
            'organisation' => [$client ? 'required' : 'nullable', 'string', 'max:150'],
            'quote' => ['required', 'string', 'min:20', 'max:1500'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'photo_file' => ['nullable', 'image', 'max:4096'],
        ];
    }

    /** A testimonial written by a student (portal) or a client (link). It waits for approval. */
    public function submitTestimonial(string $kind, array $data, Request $request, string $source, ?User $user = null, ?Student $student = null): Testimonial
    {
        $t = Testimonial::create([
            'kind' => $kind, 'name' => trim($data['name']), 'role' => trim((string) ($data['role'] ?? '')) ?: null,
            'organisation' => trim((string) ($data['organisation'] ?? '')) ?: null, 'quote' => trim($data['quote']),
            'rating' => $data['rating'] ?? null, 'website_url' => $this->url($data['website_url'] ?? null, 'website_url'),
            'video_url' => $this->url($data['video_url'] ?? null, 'video_url'),
            'photo' => $request->hasFile('photo_file') ? $request->file('photo_file')->store('website/testimonials', 'public') : null,
            'course_id' => $data['course_id'] ?? null, 'student_id' => $student?->id, 'user_id' => $user?->id,
            'source' => $source, 'status' => 'pending',
        ]);
        PortalNotifier::toUsers(PortalNotifier::userIdsWithPermission('Manage website'), 'New ' . $kind . ' testimonial to approve',
            $t->name . ($t->organisation ? ', ' . $t->organisation : '') . ': "' . Str::limit($t->quote, 90) . '"', route('website.testimonials.edit', $t), 'system');

        return $t;
    }

    /** A link a client can use to write a testimonial (no sign-in), valid for 60 days. */
    public static function clientLink(?string $organisation = null): string
    {
        return URL::temporarySignedRoute('site.testimonials.share', now()->addDays(60), array_filter(['org' => $organisation]));
    }

    // ── Helpers ─────────────────────────────────────────────────────

    protected function slug(StudentProfile $profile): string
    {
        $base = Str::slug($profile->student?->user?->name ?? 'student') ?: 'student';
        $slug = $base;
        for ($i = 2; StudentProfile::where('slug', $slug)->when($profile->id, fn ($q) => $q->whereKeyNot($profile->id))->exists(); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    protected function url(?string $v, string $field): ?string
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        if (!preg_match('#^https?://#i', $v)) $v = 'https://' . $v;
        if (!filter_var($v, FILTER_VALIDATE_URL)) throw ValidationException::withMessages([$field => 'That link doesn\'t look right.']);

        return mb_substr($v, 0, 500);
    }
}

<?php

namespace App\Http\Controllers\Website\Admin;

use App\Http\Controllers\Controller;
use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\Website\Testimonial;
use App\Services\Website\Showcase;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Website → Testimonials: what students and clients say, approved before it goes on the website. */
class TestimonialController extends Controller
{
    use StoresSiteImages;

    public function index(Request $request)
    {
        $kind = in_array($request->query('kind'), ['student', 'client'], true) ? $request->query('kind') : null;
        $status = isset(Testimonial::STATUSES[$request->query('status')]) ? $request->query('status') : null;

        return view('modules.website.testimonials.index', [
            'items' => Testimonial::with('course')->when($kind, fn ($q) => $q->where('kind', $kind))->when($status, fn ($q) => $q->where('status', $status))
                ->orderByRaw("case status when 'pending' then 0 when 'published' then 1 else 2 end")->ordered()->get(),
            'kind' => $kind, 'status' => $status,
            'pending' => Testimonial::where('status', 'pending')->count(),
            'link' => session('client_link'),
        ]);
    }

    public function create(Request $request)
    {
        return view('modules.website.testimonials.form', ['t' => new Testimonial(['kind' => $request->query('kind') === 'client' ? 'client' : 'student', 'status' => 'published', 'source' => 'staff'])] + $this->lists());
    }

    public function store(Request $request)
    {
        $t = Testimonial::create($this->validated($request) + ['source' => 'staff', 'user_id' => $request->user()->id, 'reviewed_by' => $request->user()->id]);

        return redirect()->route('website.testimonials.edit', $t)->with('success', 'Testimonial added.');
    }

    public function edit(Testimonial $testimonial)
    {
        return view('modules.website.testimonials.form', ['t' => $testimonial] + $this->lists());
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $testimonial->update($this->validated($request, $testimonial) + ['reviewed_by' => $request->user()->id]);

        return back()->with('success', 'Saved.');
    }

    public function review(Request $request, Testimonial $testimonial)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['published', 'hidden'])]]);
        $testimonial->update(['status' => $data['status'], 'reviewed_by' => $request->user()->id]);

        return back()->with('success', $data['status'] === 'published' ? 'Published.' : 'Hidden.');
    }

    public function destroy(Testimonial $testimonial)
    {
        $testimonial->delete();

        return redirect()->route('website.testimonials.index')->with('success', 'Testimonial removed.');
    }

    /** A link to send a client so they can write their own testimonial. */
    public function link(Request $request)
    {
        $data = $request->validate(['organisation' => ['nullable', 'string', 'max:150']]);

        return redirect()->route('website.testimonials.index', ['kind' => 'client'])
            ->with('client_link', Showcase::clientLink($data['organisation'] ?? null))
            ->with('success', 'Link ready. Copy it and send it to your client. It works for 60 days.');
    }

    protected function lists(): array
    {
        return [
            'courses' => Course::orderBy('title')->get(['id', 'title']),
            'students' => Student::with('user:id,name')->get()->sortBy(fn ($s) => $s->user?->name)->values(),
        ];
    }

    protected function validated(Request $request, ?Testimonial $t = null): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(Testimonial::KINDS))],
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:120'],
            'organisation' => ['nullable', 'string', 'max:150'],
            'quote' => ['required', 'string', 'max:1500'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'course_id' => ['nullable', 'integer', Rule::exists('academy_courses', 'id')],
            'student_id' => ['nullable', 'integer', Rule::exists('academy_students', 'id')],
            'status' => ['required', Rule::in(array_keys(Testimonial::STATUSES))],
            'featured' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ] + $this->imageRules());

        foreach (['website_url', 'video_url'] as $f) {
            $v = trim((string) ($data[$f] ?? ''));
            $data[$f] = $v === '' ? null : (preg_match('#^https?://#i', $v) ? $v : 'https://' . $v);
        }
        $data['photo'] = $this->imageFrom($request, $t?->photo);
        unset($data['image'], $data['image_file'], $data['remove_image']);
        $data['featured'] = $request->boolean('featured');
        $data['position'] = (int) ($data['position'] ?? 0);
        if ($data['kind'] === 'client') $data['student_id'] = $data['course_id'] = null;

        return $data;
    }
}

<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Course;
use App\Models\Academy\Lesson;
use App\Models\Academy\Section;
use App\Services\Academy\LearningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Building a course: sections and lessons, their order, and publishing. */
class ContentController extends Controller
{
    /** File types a lesson may carry. */
    public const FILE_TYPES = 'pdf,doc,docx,ppt,pptx,xls,xlsx,csv,txt,zip,png,jpg,jpeg,gif,mp3,mp4';

    public function __construct(protected LearningService $learning) {}

    public function index(Course $course)
    {
        return view('modules.academy.content.index', [
            'course'     => $course,
            'sections'   => $course->sections()->with('lessons')->get(),
            'loose'      => $course->lessons()->whereNull('section_id')->get(),
        ]);
    }

    // ── Sections ────────────────────────────────────────────────────────

    public function storeSection(Request $request, Course $course)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:2000']]);
        $course->sections()->create($data + ['position' => (int) $course->sections()->max('position') + 1]);

        return back()->with('success', 'Section added.');
    }

    public function updateSection(Request $request, Course $course, Section $section)
    {
        $this->belongs($course, $section->course_id);
        $section->update($request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:2000']]));

        return back()->with('success', 'Section updated.');
    }

    public function destroySection(Course $course, Section $section)
    {
        $this->belongs($course, $section->course_id);
        $section->delete(); // its lessons stay, without a section

        return back()->with('success', 'Section removed. Its lessons are kept under "Other lessons".');
    }

    public function moveSection(Request $request, Course $course, Section $section)
    {
        $this->belongs($course, $section->course_id);
        $this->move($course->sections()->get(), $section, $request->input('direction'));

        return back();
    }

    // ── Lessons ─────────────────────────────────────────────────────────

    public function createLesson(Request $request, Course $course)
    {
        return view('modules.academy.content.lesson-form', [
            'course'   => $course,
            'lesson'   => new Lesson(['type' => 'text', 'section_id' => $request->integer('section') ?: null, 'is_published' => true]),
            'sections' => $course->sections()->pluck('title', 'id'),
        ]);
    }

    public function storeLesson(Request $request, Course $course)
    {
        $data = $this->validated($request, $course);
        $data['position'] = (int) $course->lessons()->where('section_id', $data['section_id'])->max('position') + 1;
        $lesson = $course->lessons()->create($data + ['created_by' => $request->user()->id]);
        $this->storeFile($request, $lesson);
        $this->learning->recomputeCourse($course);

        return redirect()->route('academy.content.index', $course)->with('success', "Lesson \"{$lesson->title}\" added.");
    }

    public function editLesson(Course $course, Lesson $lesson)
    {
        $this->belongs($course, $lesson->course_id);

        return view('modules.academy.content.lesson-form', ['course' => $course, 'lesson' => $lesson, 'sections' => $course->sections()->pluck('title', 'id')]);
    }

    public function updateLesson(Request $request, Course $course, Lesson $lesson)
    {
        $this->belongs($course, $lesson->course_id);
        $data = $this->validated($request, $course, $lesson);
        if ($data['section_id'] != $lesson->section_id) {
            $data['position'] = (int) $course->lessons()->where('section_id', $data['section_id'])->max('position') + 1;
        }
        $lesson->update($data);
        $this->storeFile($request, $lesson);
        $this->learning->recomputeCourse($course);

        return redirect()->route('academy.content.index', $course)->with('success', 'Lesson updated.');
    }

    public function destroyLesson(Course $course, Lesson $lesson)
    {
        $this->belongs($course, $lesson->course_id);
        if ($lesson->file_path) Storage::disk('local')->delete($lesson->file_path);
        $lesson->delete();
        $this->learning->recomputeCourse($course);

        return back()->with('success', 'Lesson deleted.');
    }

    public function moveLesson(Request $request, Course $course, Lesson $lesson)
    {
        $this->belongs($course, $lesson->course_id);
        $this->move($course->lessons()->where('section_id', $lesson->section_id)->get(), $lesson, $request->input('direction'));

        return back();
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /** Swap an item with its neighbour, renumbering the list so positions are always 0..n. */
    protected function move($items, $item, ?string $direction): void
    {
        $items = $items->values();
        $i = $items->search(fn ($x) => $x->id === $item->id);
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($i === false || $j < 0 || $j >= $items->count()) return;

        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        foreach ($items as $pos => $x) {
            if ($x->position !== $pos) $x->update(['position' => $pos]);
        }
    }

    protected function belongs(Course $course, $courseId): void
    {
        abort_unless((int) $courseId === $course->id, 404);
    }

    protected function validated(Request $request, Course $course, ?Lesson $lesson = null): array
    {
        $type = $request->input('type');
        $data = $request->validate([
            'title'            => ['required', 'string', 'max:200'],
            'type'             => ['required', Rule::in(array_keys(Lesson::TYPES))],
            'section_id'       => ['nullable', Rule::exists('academy_sections', 'id')->where('course_id', $course->id)],
            'body'             => ['nullable', 'string', 'max:200000'],
            'video_url'        => [$type === 'video' ? 'required' : 'nullable', 'url', 'max:500'],
            'external_url'     => [$type === 'link' ? 'required' : 'nullable', 'url', 'max:500'],
            'file'             => [$type === 'file' && !$lesson?->file_path ? 'required' : 'nullable', 'file', 'mimes:' . self::FILE_TYPES, 'max:' . (int) config('qudroid.academy.max_upload_kb', 51200)],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ], ['video_url.required' => 'Paste the YouTube or Vimeo link.', 'external_url.required' => 'Enter the link.', 'file.required' => 'Choose a file to upload.']);

        unset($data['file']);
        $data['section_id'] = $data['section_id'] ?? null;
        $data['is_preview'] = $request->boolean('is_preview');
        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }

    protected function storeFile(Request $request, Lesson $lesson): void
    {
        if (!$request->hasFile('file')) return;

        $file = $request->file('file');
        $old = $lesson->file_path;
        $lesson->update([
            'file_path' => $file->store('academy/lessons/' . $lesson->course_id, 'local'),
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_size' => $file->getSize(),
        ]);
        if ($old) Storage::disk('local')->delete($old);
    }
}

<?php

namespace App\Http\Controllers\Website\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website\Form;
use App\Models\Website\FormEntry;
use App\Services\Website\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Website → Forms: build forms, read what people sent. */
class FormController extends Controller
{
    use StoresSiteImages;

    public function index()
    {
        return view('modules.website.forms.index', [
            'forms' => Form::withCount(['entries', 'entries as new_count' => fn ($q) => $q->where('status', 'new')])->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('modules.website.forms.form', ['form' => new Form([
            'status' => 'open', 'to_crm' => false, 'button_label' => 'Send', 'success_message' => 'Thank you. We have received your details.',
            'fields' => [
                ['key' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true],
            ],
        ])]);
    }

    public function store(Request $request)
    {
        $form = Form::create($this->validated($request));

        return redirect()->route('website.forms.edit', $form)->with('success', 'Form created. Add it to a page with a Form block, or share its link.');
    }

    public function edit(Form $form)
    {
        return view('modules.website.forms.form', ['form' => $form]);
    }

    public function update(Request $request, Form $form)
    {
        $form->update($this->validated($request, $form));

        return back()->with('success', 'Form saved.');
    }

    public function destroy(Form $form)
    {
        abort_if($form->slug === 'contact', 422, 'The contact form is used by the Contact page and cannot be deleted. Close it instead.');
        $form->delete();

        return redirect()->route('website.forms.index')->with('success', 'Form and its entries deleted.');
    }

    public function entries(Request $request, Form $form)
    {
        $status = in_array($request->query('status'), ['new', 'read', 'archived'], true) ? $request->query('status') : null;
        $entries = $form->entries()->with('deal')->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'archived'))
            ->latest('id')->paginate(25)->withQueryString();

        return view('modules.website.forms.entries', ['form' => $form, 'entries' => $entries, 'status' => $status]);
    }

    public function export(Form $form)
    {
        $fields = collect($form->fields ?? []);

        return response()->streamDownload(function () use ($form, $fields) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['Received', 'Status'], $fields->pluck('label')->all(), ['Page', 'IP address']));
            $form->entries()->orderBy('id')->chunk(500, function ($rows) use ($out, $fields) {
                foreach ($rows as $e) {
                    $values = $fields->map(function ($f) use ($e) {
                        $v = $e->data[$f['key']] ?? '';
                        $v = is_bool($v) ? ($v ? 'Yes' : 'No') : (string) $v;

                        return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;   // no spreadsheet formulas
                    })->all();
                    fputcsv($out, array_merge([$e->created_at->format('Y-m-d H:i'), $e->status], $values, [$e->page, $e->ip_address]));
                }
            });
            fclose($out);
        }, Str::slug($form->name) . '-entries-' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function entryStatus(Request $request, FormEntry $entry)
    {
        $entry->update($request->validate(['status' => ['required', Rule::in(['new', 'read', 'archived'])]]));

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function destroyEntry(FormEntry $entry)
    {
        $entry->delete();

        return back()->with('success', 'Entry deleted.');
    }

    protected function validated(Request $request, ?Form $form = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80'],
            'intro' => ['nullable', 'string', 'max:1000'],
            'button_label' => ['nullable', 'string', 'max:40'],
            'success_message' => ['nullable', 'string', 'max:500'],
            'notify' => ['nullable', 'string', 'max:500'],
            'to_crm' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['open', 'closed'])],
            'fields' => ['required', 'array', 'min:1', 'max:30'],
            'fields.*.label' => ['nullable', 'string', 'max:150'],
            'fields.*.key' => ['nullable', 'string', 'max:40'],
            'fields.*.type' => ['nullable', Rule::in(array_keys(Form::TYPES))],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'string', 'max:2000'],
            'fields.*.help' => ['nullable', 'string', 'max:200'],
        ]);

        $emails = collect(preg_split('/[\s,;]+/', (string) ($data['notify'] ?? '')))->filter()->unique();
        if ($bad = $emails->first(fn ($e) => !filter_var($e, FILTER_VALIDATE_EMAIL))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['notify' => "\"{$bad}\" is not an e-mail address."]);
        }
        $data['notify'] = $emails->implode(', ') ?: null;
        $data['fields'] = Site::cleanFields($data['fields']);
        if (!$data['fields']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['fields' => 'Add at least one field with a label.']);
        }
        $data['slug'] = $form?->slug === 'contact' ? 'contact' : $this->uniqueSlug(Form::class, $this->slugFrom($data['slug'] ?? null, $data['name']), $form?->id);
        $data['to_crm'] = $request->boolean('to_crm');
        $data['button_label'] = ($data['button_label'] ?? null) ?: 'Send';

        return $data;
    }
}

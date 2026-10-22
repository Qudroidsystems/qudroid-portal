<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Academy\Programme;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProgrammeController extends Controller
{
    public function index()
    {
        return view('modules.academy.programmes.index', [
            'programmes' => Programme::withCount('courses')->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Programme::create($this->validated($request));

        return back()->with('success', 'Programme added.');
    }

    public function update(Request $request, Programme $programme)
    {
        $programme->update($this->validated($request, $programme));

        return back()->with('success', 'Programme updated.');
    }

    public function destroy(Programme $programme)
    {
        if ($programme->courses()->withTrashed()->exists()) {
            return back()->with('error', 'Move or delete this programme\'s courses first. You can also mark it inactive.');
        }
        $programme->delete();

        return back()->with('success', 'Programme deleted.');
    }

    protected function validated(Request $request, ?Programme $programme = null): array
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'position'    => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active'   => ['nullable', 'boolean'],
        ]);
        $data['slug'] = Str::slug($data['name']);
        $request->merge(['slug' => $data['slug']])->validate([
            'slug' => [Rule::unique('academy_programmes', 'slug')->ignore($programme?->id)],
        ], ['slug.unique' => 'A programme with this name already exists.']);
        $data['position'] = (int) ($data['position'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}

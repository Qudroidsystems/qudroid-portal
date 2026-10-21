<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiGuidanceSetting;
use App\Services\Ai\GuidanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin settings for the mobile app's learning & career guidance: which AI
 * the school uses (or the built-in rules only), the model and API key, and
 * who sees it.
 */
class AiGuidanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:Manage AI guidance');
    }

    public function index()
    {
        $s = AiGuidanceSetting::current();
        $stats = Schema::hasTable('ai_guidance_reports') ? [
            'reports'  => DB::table('ai_guidance_reports')->count(),
            'students' => DB::table('ai_guidance_reports')->distinct()->count('student_id'),
            'ai'       => DB::table('ai_guidance_reports')->where('provider', '!=', 'rules')->count(),
            'month'    => DB::table('ai_guidance_reports')->where('created_at', '>=', now()->startOfMonth())->count(),
        ] : ['reports' => 0, 'students' => 0, 'ai' => 0, 'month' => 0];

        return view('admin.ai-guidance.index', [
            'pagetitle' => 'AI Learning Guidance',
            's' => $s,
            'providers' => AiGuidanceSetting::PROVIDERS,
            'stats' => $stats,
            'hasKey' => !empty($s->api_key),
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'enabled'            => 'nullable|boolean',
            'provider'           => 'required|in:' . implode(',', array_keys(AiGuidanceSetting::PROVIDERS)),
            'model'              => 'nullable|string|max:100',
            'api_key'            => 'nullable|string|max:500',
            'remove_key'         => 'nullable|boolean',
            'for_parents'        => 'nullable|boolean',
            'for_students'       => 'nullable|boolean',
            'refreshes_per_term' => 'required|integer|min:1|max:20',
            'school_context'     => 'nullable|string|max:1000',
            'action'             => 'nullable|in:save,test',
        ]);

        $s = AiGuidanceSetting::current();
        $s->fill([
            'enabled'            => (bool) ($data['enabled'] ?? false),
            'provider'           => $data['provider'],
            'model'              => ($data['model'] ?? null) ?: null,
            'for_parents'        => (bool) ($data['for_parents'] ?? false),
            'for_students'       => (bool) ($data['for_students'] ?? false),
            'refreshes_per_term' => $data['refreshes_per_term'],
            'school_context'     => $data['school_context'] ?? null,
            'updated_by'         => $request->user()->id,
        ]);
        if (!empty($data['remove_key'])) {
            $s->api_key = null;
        } elseif (!empty($data['api_key'])) {
            $s->api_key = trim($data['api_key']);
        }
        if ($s->provider !== 'rules' && empty($s->api_key)) {
            $s->save();
            return back()->with('error', 'Saved, but no API key is set — the built-in rules are used until you add one.');
        }
        $s->save();

        if (($data['action'] ?? 'save') === 'test' && $s->provider !== 'rules') {
            $err = app(GuidanceService::class)->test($s);
            return back()->with($err ? 'error' : 'success', $err ? 'The AI did not answer: ' . $err : 'Connected — the AI answered correctly. Settings saved.');
        }

        return back()->with('success', 'Guidance settings saved.');
    }

    /** Forget stored reports so they are rebuilt with the new settings. */
    public function reset()
    {
        if (Schema::hasTable('ai_guidance_reports')) DB::table('ai_guidance_reports')->delete();
        return back()->with('success', 'Stored guidance cleared. It will be rebuilt the next time each student opens it.');
    }
}

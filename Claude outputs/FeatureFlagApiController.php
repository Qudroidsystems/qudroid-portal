<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use Illuminate\Http\Request;

/**
 * The remote control portal talks to these endpoints (behind the
 * remote.portal middleware). GET reads the current 1/0 map; POST sets it.
 */
class FeatureFlagApiController extends Controller
{
    /** Current flags as {key: 0|1}. This is what the remote reads. */
    public function index()
    {
        return response()->json([
            'ok' => true,
            'flags' => collect(FeatureFlag::map())->map(fn ($v) => $v ? 1 : 0),
            'at' => now()->toIso8601String(),
        ]);
    }

    /** Simple heartbeat: always returns 1 so the remote knows we're alive. */
    public function health()
    {
        return response()->json(['ok' => 1]);
    }

    /**
     * Set flags. Accepts either:
     *   { "flags": { "dashboard": 1, "results": 0 } }
     * or a single flag:
     *   { "key": "dashboard", "value": 1 }
     */
    public function sync(Request $request)
    {
        $data = $request->validate([
            'flags' => 'required_without:key|array',
            'key' => 'required_without:flags|string|max:60',
            'value' => 'required_with:key',
        ]);

        $flags = $data['flags'] ?? [$data['key'] => $data['value']];
        $summary = FeatureFlag::applyRemote($flags, 'api');

        return response()->json([
            'ok' => true,
            'applied' => $summary['changed'],
            'skipped' => $summary['skipped'],
            'flags' => collect(FeatureFlag::map())->map(fn ($v) => $v ? 1 : 0),
        ]);
    }
}

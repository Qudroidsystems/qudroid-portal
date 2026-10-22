<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Activity;
use App\Models\Crm\Deal;
use App\Services\Crm\CrmService;
use Illuminate\Http\Request;

class CrmController extends Controller
{
    public function __invoke(Request $request, CrmService $crm)
    {
        $user = $request->user();

        return view('modules.crm.index', [
            'stats'     => $crm->stats(),
            'followUps' => Activity::with('subject', 'company')->pending()->where('assigned_to', $user->id)->orderBy('due_at')->limit(15)->get(),
            'closing'   => Deal::with('company', 'owner')->open()->whereNotNull('expected_close_on')->orderBy('expected_close_on')->limit(8)->get(),
            'recent'    => Activity::with('user', 'subject', 'company')->where('type', '!=', 'follow_up')->latest()->limit(10)->get(),
            'crm'       => $crm,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Support\Money;

class FinanceController extends Controller
{
    public function index()
    {
        $sum = fn ($rows, string $col) => Money::major($rows->sum(fn ($r) => Money::minor($r->$col)));
        $open = Invoice::open()->get(['total', 'amount_paid', 'due_date', 'status']);
        $outstanding = Money::major($open->sum(fn ($i) => $i->balanceMinor()));
        $overdue = $open->filter->isOverdue();

        return view('modules.finance.index', [
            'outstanding'   => $outstanding,
            'openCount'     => $open->count(),
            'overdue'       => Money::major($overdue->sum(fn ($i) => $i->balanceMinor())),
            'overdueCount'  => $overdue->count(),
            'thisMonth'     => $sum(Payment::where('status', 'confirmed')->where('confirmed_at', '>=', now()->startOfMonth())->get(['amount']), 'amount'),
            'today'         => $sum(Payment::where('status', 'confirmed')->where('confirmed_at', '>=', today())->get(['amount']), 'amount'),
            'recent'        => Payment::with('user')->where('status', 'confirmed')->latest('confirmed_at')->limit(8)->get(),
            'overdueList'   => Invoice::overdue()->with('user')->orderBy('due_date')->limit(8)->get(),
        ]);
    }
}

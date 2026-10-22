<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Services\Hr\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function __construct(protected LeaveService $leave) {}

    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');
        $q = LeaveRequest::with('employee.user', 'type', 'decider')
            ->when($status !== 'all', fn ($w) => $w->where('status', $status))
            ->when($request->filled('type'), fn ($w) => $w->where('leave_type_id', $request->type))
            ->when($request->filled('q'), fn ($w) => $w->whereHas('employee.user', fn ($u) => $u->where('name', 'like', '%' . $request->q . '%')));

        return view('modules.hr.leave.index', [
            'requests' => $q->orderBy($status === 'pending' ? 'starts_on' : 'id', $status === 'pending' ? 'asc' : 'desc')->paginate(30)->withQueryString(),
            'types' => LeaveType::orderBy('name')->get(),
            'counts' => LeaveRequest::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'status' => $status,
        ]);
    }

    public function show(Request $request, LeaveRequest $leave)
    {
        $leave->load('employee.user', 'employee.manager', 'type', 'decider');
        $user = $request->user();
        abort_unless($leave->employee->user_id === $user->id || $leave->employee->manager_id === $user->id || $user->can('View employees'), 403);

        return view('modules.hr.leave.show', [
            'leave' => $leave,
            'canDecide' => $leave->status === 'pending' && $this->leave->canDecide($leave, $user),
            'balances' => $this->leave->balances($leave->employee, $leave->starts_on->year),
            'overlapping' => LeaveRequest::with('employee.user', 'type')->where('status', 'approved')->where('id', '!=', $leave->id)
                ->whereDate('starts_on', '<=', $leave->ends_on)->whereDate('ends_on', '>=', $leave->starts_on)
                ->whereHas('employee', fn ($q) => $leave->employee->department_id
                    ? $q->where('department_id', $leave->employee->department_id) : $q->where('manager_id', $leave->employee->manager_id))
                ->get(),
        ]);
    }

    public function decide(Request $request, LeaveRequest $leave)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'decision_note' => ['nullable', 'string', 'max:255']]);
        $this->leave->decide($leave, $request->user(), $data['decision'] === 'approve', $data['decision_note'] ?? null);

        return back()->with('success', $data['decision'] === 'approve' ? 'Leave approved.' : 'Leave declined.');
    }

    public function cancel(Request $request, LeaveRequest $leave)
    {
        $this->leave->cancel($leave, $request->user());

        return back()->with('success', 'Leave request cancelled.');
    }

    // ── Self-service ────────────────────────────────────────────────────

    public function mine(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        return view('modules.hr.me.leave', [
            'employee' => $employee,
            'balances' => $employee ? $this->leave->balances($employee) : collect(),
            'requests' => $employee ? $employee->leaveRequests()->with('type', 'decider')->latest('starts_on')->limit(30)->get() : collect(),
            'types' => LeaveType::where('is_active', true)->orderByDesc('is_annual')->orderBy('name')->get(),
            'team' => $this->leave->toDecide($user)->reject(fn ($l) => !$this->leave->canDecide($l, $user)),
            'holidays' => Holiday::whereDate('date', '>=', today())->orderBy('date')->limit(6)->get(),
        ]);
    }

    public function storeMine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403);
        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:hr_leave_types,id'],
            'starts_on' => ['required', 'date', 'after_or_equal:' . today()->subDays(30)->toDateString()],
            'ends_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $leave = $this->leave->request($employee, $data, $request->user());

        return redirect()->route('me.leave')->with('success', "Leave request sent: {$leave->days} working days. You'll be told when it is decided.");
    }

    // ── Settings ────────────────────────────────────────────────────────

    public function settings()
    {
        return view('modules.hr.leave.settings', [
            'types' => LeaveType::orderByDesc('is_annual')->orderBy('name')->get(),
            'holidays' => Holiday::whereYear('date', '>=', today()->year)->orderBy('date')->get(),
        ]);
    }

    public function storeType(Request $request)
    {
        LeaveType::create($this->typeData($request));

        return back()->with('success', 'Leave type added.');
    }

    public function updateType(Request $request, LeaveType $type)
    {
        $type->update($this->typeData($request, $type));

        return back()->with('success', 'Leave type saved.');
    }

    public function storeHoliday(Request $request)
    {
        $data = $request->validate(['date' => ['required', 'date', 'unique:hr_holidays,date'], 'name' => ['required', 'string', 'max:100']]);
        Holiday::create($data);

        return back()->with('success', 'Holiday added.');
    }

    public function destroyHoliday(Holiday $holiday)
    {
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }

    protected function typeData(Request $request, ?LeaveType $type = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('hr_leave_types', 'name')->ignore($type)],
            'days_per_year' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        return $data + ['is_paid' => $request->boolean('is_paid'), 'is_active' => $request->boolean('is_active', true)];
    }
}

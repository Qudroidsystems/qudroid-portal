<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\PayrollRun;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrController extends Controller
{
    public function index(Request $request)
    {
        $today = today();

        return view('modules.hr.index', [
            'headcount' => Employee::active()->count(),
            'onLeave'   => LeaveRequest::with('employee.user', 'type')->where('status', 'approved')
                ->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)->get(),
            'pending'   => LeaveRequest::with('employee.user', 'type')->where('status', 'pending')->orderBy('starts_on')->limit(8)->get(),
            'pendingCount' => LeaveRequest::where('status', 'pending')->count(),
            'upcoming'  => LeaveRequest::with('employee.user', 'type')->where('status', 'approved')
                ->whereDate('starts_on', '>', $today)->whereDate('starts_on', '<=', $today->copy()->addDays(30))->orderBy('starts_on')->limit(8)->get(),
            'byDepartment' => Department::withCount(['employees' => fn ($q) => $q->active()])->orderBy('name')->get(),
            'unassigned' => Employee::active()->whereNull('department_id')->count(),
            'lastRun'   => $request->user()->can('Manage payroll') || $request->user()->can('Approve finance') ? PayrollRun::orderByDesc('period')->first() : null,
            'newStarters' => Employee::with('user')->active()->whereDate('start_date', '>=', $today->copy()->subDays(30))->orderByDesc('start_date')->get(),
        ]);
    }

    public function departments()
    {
        return view('modules.hr.departments', [
            'departments' => Department::with('head')->withCount(['employees' => fn ($q) => $q->active()])->orderBy('name')->get(),
            'staff' => User::staff()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeDepartment(Request $request)
    {
        Department::create($this->validated($request));

        return back()->with('success', 'Department added.');
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $department->update($this->validated($request, $department));

        return back()->with('success', 'Department saved.');
    }

    public function destroyDepartment(Department $department)
    {
        $department->delete();   // employees keep their records, with no department

        return back()->with('success', 'Department removed.');
    }

    protected function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('hr_departments', 'name')->ignore($department)],
            'head_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

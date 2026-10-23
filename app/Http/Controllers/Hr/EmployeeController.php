<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use App\Models\User;
use App\Services\Hr\EmployeeService;
use App\Services\Hr\LeaveService;
use App\Services\Hr\PayeCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function __construct(protected EmployeeService $employees) {}

    public function index(Request $request)
    {
        $q = Employee::with('user', 'department')
            ->when($request->filled('q'), function ($w) use ($request) {
                $term = '%' . $request->q . '%';
                $w->where(fn ($x) => $x->where('employee_no', 'like', $term)->orWhere('job_title', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->when($request->filled('department'), fn ($w) => $w->where('department_id', $request->department))
            ->where('status', $request->input('status', 'active') ?: 'active');

        return view('modules.hr.employees.index', [
            'employees' => $q->orderBy('employee_no')->paginate(30)->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
            'counts' => Employee::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function create()
    {
        return view('modules.hr.employees.form', $this->formData(new Employee([
            'employment_type' => 'full_time', 'pension_enrolled' => true, 'nhf_enrolled' => false, 'annual_leave_days' => 20, 'start_date' => today(),
        ])) + [
            'accounts' => User::staff()->whereDoesntHave('employee')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'user_id' => ['nullable', 'exists:users,id'],
            'name' => ['nullable', 'required_without:user_id', 'string', 'max:150'],
            'email' => ['nullable', 'required_without:user_id', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);
        [$employee, $password] = $this->employees->create($this->withPay($request, $data));
        \App\Support\BusinessUnits::apply($request, $employee);

        $redirect = redirect()->route('hr.employees.show', $employee)->with('success', $employee->user->name . ' added as ' . $employee->employee_no . '.');

        return $password ? $redirect->with('staff_password', $password) : $redirect;
    }

    public function show(Request $request, Employee $employee, LeaveService $leave, PayeCalculator $paye)
    {
        $employee->load('user.roles', 'department', 'manager');
        $seePay = $request->user()->can('Manage employees') || $request->user()->can('Manage payroll');

        return view('modules.hr.employees.show', [
            'employee' => $employee,
            'seePay' => $seePay,
            'estimate' => $seePay && $employee->monthlyGrossMinor() > 0 ? $paye->month($employee) : null,
            'balances' => $leave->balances($employee),
            'requests' => $employee->leaveRequests()->with('type')->latest('starts_on')->limit(10)->get(),
            'payslips' => $seePay ? $employee->payslips()->with('run')->latest('id')->limit(12)->get() : collect(),
            'reports' => Employee::with('user')->active()->where('manager_id', $employee->user_id)->get(),
        ]);
    }

    public function edit(Employee $employee)
    {
        return view('modules.hr.employees.form', $this->formData($employee->load('user')));
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate($this->rules() + [
            'status' => ['required', Rule::in(array_keys(Employee::STATUSES))],
            'exit_date' => ['nullable', 'date'],
            'exit_reason' => ['nullable', 'string', 'max:255'],
        ]);
        $this->employees->update($employee, $this->withPay($request, $data));
        \App\Support\BusinessUnits::apply($request, $employee);

        return redirect()->route('hr.employees.show', $employee)->with('success', 'Saved.');
    }

    protected function formData(Employee $employee): array
    {
        return [
            'employee' => $employee,
            'departments' => Department::orderBy('name')->get(),
            'managers' => User::staff()->where('id', '!=', $employee->user_id ?? 0)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /** Pay fields only count from someone who may set pay. */
    protected function withPay(Request $request, array $data): array
    {
        if (!$request->user()->can('Manage payroll')) {
            return collect($data)->except(EmployeeService::PAY_FIELDS)->all();
        }
        $data['pension_enrolled'] = $request->boolean('pension_enrolled');
        $data['nhf_enrolled'] = $request->boolean('nhf_enrolled');

        return $data;
    }

    protected function rules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:999999999'];

        return [
            'job_title' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:hr_departments,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'employment_type' => ['required', Rule::in(array_keys(Employee::TYPES))],
            'start_date' => ['nullable', 'date'],
            'annual_leave_days' => ['required', 'integer', 'min:0', 'max:60'],
            'next_of_kin_name' => ['nullable', 'string', 'max:150'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'basic_salary' => $money, 'housing_allowance' => $money, 'transport_allowance' => $money, 'other_allowance' => $money, 'annual_rent' => $money,
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:20'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'pension_pfa' => ['nullable', 'string', 'max:100'],
            'pension_pin' => ['nullable', 'string', 'max:30'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'nhf_number' => ['nullable', 'string', 'max:30'],
        ];
    }
}

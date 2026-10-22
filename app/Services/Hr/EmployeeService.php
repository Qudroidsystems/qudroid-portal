<?php

namespace App\Services\Hr;

use App\Models\Hr\Employee;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Employee records. Every employee is a staff account in the portal; HR
 * either picks an existing staff account or creates one (with a temporary
 * password and the Staff role) when adding the employee.
 */
class EmployeeService
{
    public const PAY_FIELDS = ['basic_salary', 'housing_allowance', 'transport_allowance', 'other_allowance', 'annual_rent',
        'pension_enrolled', 'nhf_enrolled', 'bank_name', 'account_number', 'account_name', 'pension_pfa', 'pension_pin', 'tax_id', 'nhf_number'];

    /**
     * @return array{0: Employee, 1: ?string} the employee and, for a new account, its temporary password
     */
    public function create(array $data): array
    {
        $password = null;

        $employee = DB::transaction(function () use ($data, &$password) {
            if (!empty($data['user_id'])) {
                $user = User::findOrFail($data['user_id']);
                $this->check(!$user->isStaff(), 'user_id', 'Only staff accounts can be employees.');
            } else {
                $this->check(empty($data['name']) || empty($data['email']), 'email', 'Choose a staff account or enter a name and email for a new one.');
                $this->check(User::where('email', $data['email'])->exists(), 'email', 'That email already has an account. Choose it from the list instead.');
                $password = Str::password(10, symbols: false);
                $user = User::create(['name' => trim($data['name']), 'email' => trim($data['email']), 'phone_number' => $data['phone'] ?? null,
                    'user_type' => User::TYPE_STAFF, 'password' => $password]);
                $user->forceFill(['must_change_password' => true])->save();
                $user->assignRole('Staff');
            }
            $this->check(Employee::where('user_id', $user->id)->exists(), 'user_id', $user->name . ' already has an employee record.');

            return Employee::create($this->clean($data) + ['user_id' => $user->id, 'employee_no' => $this->nextNumber(), 'status' => 'active']);
        });

        return [$employee, $password];
    }

    public function update(Employee $employee, array $data): Employee
    {
        $data = $this->clean($data);
        if (($data['status'] ?? null) === 'exited') {
            $data['exit_date'] ??= today();
        } elseif (isset($data['status'])) {
            $data['exit_date'] = null;
            $data['exit_reason'] = null;
        }
        $this->check(isset($data['manager_id']) && (int) $data['manager_id'] === $employee->user_id, 'manager_id', 'Someone can\'t be their own manager.');
        $employee->update($data);

        return $employee;
    }

    protected function clean(array $data): array
    {
        foreach (['basic_salary', 'housing_allowance', 'transport_allowance', 'other_allowance'] as $f) {
            if (array_key_exists($f, $data)) $data[$f] = Money::major(max(0, Money::minor($data[$f] ?? 0)));
        }
        if (array_key_exists('annual_rent', $data)) {
            $data['annual_rent'] = ($data['annual_rent'] ?? '') === '' ? null : Money::major(max(0, Money::minor($data['annual_rent'])));
        }

        return collect($data)->only((new Employee)->getFillable())->except(['user_id', 'employee_no'])->all();
    }

    /** QS-0001, QS-0002 … never reused. */
    protected function nextNumber(): string
    {
        $row = DB::table('finance_sequences')->where('key', 'employee')->where('year', 0)->lockForUpdate()->first();
        $n = max((int) ($row->last ?? 0), (int) Employee::count()) + 1;
        $row ? DB::table('finance_sequences')->where('key', 'employee')->where('year', 0)->update(['last' => $n])
             : DB::table('finance_sequences')->insert(['key' => 'employee', 'year' => 0, 'last' => $n]);

        return config('qudroid.payroll.employee_prefix', 'QS') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}

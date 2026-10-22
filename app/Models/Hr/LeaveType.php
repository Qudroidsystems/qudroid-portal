<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $table = 'hr_leave_types';

    protected $fillable = ['name', 'days_per_year', 'is_paid', 'is_annual', 'is_active'];

    protected $casts = ['is_paid' => 'boolean', 'is_annual' => 'boolean', 'is_active' => 'boolean'];

    /** Days allowed a year for this person; null = no limit. */
    public function allowanceFor(Employee $employee): ?int
    {
        return $this->is_annual ? (int) $employee->annual_leave_days : $this->days_per_year;
    }
}

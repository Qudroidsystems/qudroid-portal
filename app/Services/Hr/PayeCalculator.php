<?php

namespace App\Services\Hr;

use App\Models\Hr\Employee;

/**
 * Works out one month's pay for an employee, in minor units (kobo).
 *
 *   pension (employee)  = pension_employee % of basic + housing + transport
 *   NHF                 = nhf_rate % of basic (when enrolled)
 *   taxable a year      = 12 × (regular gross − pension − NHF) − rent relief
 *   PAYE this month     = tax on that a year ÷ 12, plus the extra tax a
 *                         bonus paid this month adds to the year's tax
 *
 * Unpaid leave reduces every pay item by unpaid days ÷ working days in a month.
 * Rates and bands come from config('qudroid.payroll').
 */
class PayeCalculator
{
    public function __construct(protected ?array $config = null)
    {
        $this->config ??= config('qudroid.payroll');
    }

    /**
     * @return array{basic:int, housing:int, transport:int, other_allowance:int, bonus:int, unpaid_deduction:int, gross:int,
     *               pension:int, nhf:int, paye:int, other_deductions:int, net:int, employer_pension:int, workings:array}
     */
    public function month(Employee $e, int $bonus = 0, int $otherDeductions = 0, float $unpaidDays = 0): array
    {
        $c = $this->config;
        $items = [
            'basic'           => $this->minor($e->basic_salary),
            'housing'         => $this->minor($e->housing_allowance),
            'transport'       => $this->minor($e->transport_allowance),
            'other_allowance' => $this->minor($e->other_allowance),
        ];
        $full = array_sum($items);
        $workDays = max(1, (int) $c['working_days_month']);
        $unpaidDays = min($unpaidDays, $workDays);
        $factor = 1 - $unpaidDays / $workDays;

        $regular = (int) round($full * $factor);
        $unpaid = $full - $regular;
        $pensionBase = (int) round(($items['basic'] + $items['housing'] + $items['transport']) * $factor);

        $pension = $e->pension_enrolled ? $this->pct($pensionBase, $c['pension_employee']) : 0;
        $employerPension = $e->pension_enrolled ? $this->pct($pensionBase, $c['pension_employer']) : 0;
        $nhf = $e->nhf_enrolled ? $this->pct((int) round($items['basic'] * $factor), $c['nhf_rate']) : 0;

        $rentRelief = min($this->pct($this->minor($e->annual_rent ?? 0), $c['rent_relief_rate']), (int) $c['rent_relief_cap'] * 100);
        $taxableYear = max(0, 12 * ($regular - $pension - $nhf) - $rentRelief);
        [$taxYear, $bands] = $this->annualTax($taxableYear);
        $bonusTax = $bonus > 0 ? $this->annualTax($taxableYear + $bonus)[0] - $taxYear : 0;
        $paye = (int) round($taxYear / 12) + $bonusTax;

        $gross = $regular + $bonus;
        $net = $gross - $pension - $nhf - $paye - $otherDeductions;

        return $items + [
            'bonus' => $bonus, 'unpaid_deduction' => $unpaid, 'gross' => $gross,
            'pension' => $pension, 'nhf' => $nhf, 'paye' => $paye, 'other_deductions' => $otherDeductions,
            'net' => $net, 'employer_pension' => $employerPension,
            'workings' => [
                'regular_gross' => $regular, 'rent_relief' => $rentRelief, 'taxable_year' => $taxableYear,
                'tax_year' => $taxYear, 'bonus_tax' => $bonusTax, 'bands' => $bands, 'unpaid_days' => $unpaidDays,
            ],
        ];
    }

    /**
     * Tax on a year's taxable income.
     *
     * @return array{0:int, 1:array<int, array{from:int, to:?int, rate:float, tax:int}>}
     */
    public function annualTax(int $taxable): array
    {
        $tax = 0;
        $from = 0;
        $rows = [];
        foreach ($this->config['tax_bands'] as [$width, $rate]) {
            $to = $width === null ? null : $from + (int) $width * 100;
            $slice = max(0, ($to === null ? $taxable : min($taxable, $to)) - $from);
            $bandTax = (int) round($slice * $rate / 100);
            if ($slice > 0) $rows[] = ['from' => $from, 'to' => $to, 'rate' => (float) $rate, 'amount' => $slice, 'tax' => $bandTax];
            $tax += $bandTax;
            if ($to === null || $taxable <= $to) break;
            $from = $to;
        }

        return [$tax, $rows];
    }

    protected function pct(int $amount, float|int $rate): int
    {
        return (int) round($amount * $rate / 100);
    }

    protected function minor($v): int
    {
        return \App\Support\Money::minor($v ?? 0);
    }
}

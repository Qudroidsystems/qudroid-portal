<?php

namespace Database\Seeders;

use App\Models\Academy\Cohort;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Programme;
use App\Models\Academy\Student;
use App\Models\BusinessUnit;
use App\Models\Checkin\Event as CheckinEvent;
use App\Models\Checkin\Place;
use App\Models\Checkin\Record;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\Drive\Folder;
use App\Models\Finance\Account;
use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveType;
use App\Models\Inventory\Category;
use App\Models\Inventory\Item;
use App\Models\Inventory\Location;
use App\Models\Inventory\Supplier;
use App\Models\Meetings\Meeting;
use App\Models\Meetings\Room;
use App\Models\User;
use App\Services\Academy\AttendanceService;
use App\Services\Academy\EnrollmentService;
use App\Services\Checkin\CheckinService;
use App\Services\Connect\ConnectService;
use App\Services\Crm\CrmService;
use App\Services\Finance\ExpenseService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Hr\EmployeeService;
use App\Services\Hr\LeaveService;
use App\Services\Hr\PayrollService;
use App\Services\Inventory\AssetService;
use App\Services\Inventory\PurchaseOrderService;
use App\Services\Inventory\StockService;
use App\Services\Meetings\MeetingService;
use App\Services\Projects\ProjectService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sample data for trying the portal: php artisan db:seed --class=DemoSeeder
 *
 * Fills every module with a small Nigerian company's worth of records, made
 * through the same services the pages use, so balances, ledgers and registers
 * add up. Everyone it creates has an @demo.qudroid.test address and the
 * password below. It runs once (it stops if the demo people already exist) and
 * will not run in production unless DEMO_SEED_IN_PRODUCTION=true.
 */
class DemoSeeder extends Seeder
{
    public const DOMAIN = 'demo.qudroid.test';
    public const PASSWORD = 'Demo@2026!';

    /** @var array<string, User> */
    protected array $u = [];

    protected Carbon $today;

    public function run(): void
    {
        if (app()->isProduction() && !filter_var(env('DEMO_SEED_IN_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->command?->error('Not adding demo data to a production site. Set DEMO_SEED_IN_PRODUCTION=true if you really mean to.');

            return;
        }
        if (User::where('email', 'like', '%@' . self::DOMAIN)->exists()) {
            $this->command?->warn('Demo data is already there (people with @' . self::DOMAIN . ' addresses). Nothing added.');

            return;
        }

        $this->call([PermissionSeeder::class, RoleSeeder::class]);

        // Nothing leaves the building: no e-mails, no live updates.
        Mail::fake();
        config(['broadcasting.default' => 'null', 'queue.default' => 'sync']);
        $this->today = now()->startOfDay();

        // All or nothing: a failure part-way leaves the database as it was.
        try {
            DB::transaction(function () {
                $this->people();
                $this->hr();
                $this->crm();
                $this->projects();
                $this->finance();
                $this->academy();
                $this->inventory();
                $this->meetings();
                $this->chat();
                $this->checkin();
                $this->drive();
            });
        } finally {
            Carbon::setTestNow();
        }

        $this->command?->info('Demo data added. Sign in as any of these with the password ' . self::PASSWORD . ':');
        foreach (['md' => 'Managing Director', 'finance' => 'Finance Officer', 'hr' => 'HR Manager', 'pm' => 'Project Manager',
            'academy' => 'Academy Administrator', 'instructor' => 'Instructor', 'staff1' => 'Staff', 'student1' => 'Student', 'client1' => 'Client'] as $k => $role) {
            $this->command?->line(sprintf('  %-22s %s', $role, $this->u[$k]->email));
        }
    }

    /** Run $fn as though it were $when, so dates, numbers and ledgers line up. */
    protected function at(Carbon|string $when, callable $fn): mixed
    {
        Carbon::setTestNow($when instanceof Carbon ? $when : Carbon::parse($when));
        try {
            return $fn();
        } finally {
            Carbon::setTestNow();
        }
    }

    protected function day(int $daysFromToday, string $time = '10:00'): Carbon
    {
        return $this->today->copy()->addDays($daysFromToday)->setTimeFromTimeString($time);
    }

    /** The nearest weekday on or before the date. */
    protected function weekday(Carbon $d): Carbon
    {
        while ($d->isWeekend()) $d->subDay();

        return $d;
    }

    protected function user(string $key, string $name, string $type, ?string $role, array $extra = []): User
    {
        $email = Str::of($name)->lower()->ascii()->replace(' ', '.')->append('@' . self::DOMAIN)->toString();
        $user = User::create(['name' => $name, 'email' => $email, 'user_type' => $type, 'password' => self::PASSWORD] + $extra);
        $user->forceFill(['must_change_password' => false, 'email_verified_at' => now()])->save();
        if ($role) $user->assignRole($role);

        return $this->u[$key] = $user;
    }

    // ── People ──────────────────────────────────────────────────────

    protected function people(): void
    {
        $staff = [
            ['md', 'Adaeze Okafor', 'Managing Director', 'Managing Director', 'female'],
            ['finance', 'Chukwuemeka Nwosu', 'Finance Officer', 'Finance & Accounts Lead', 'male'],
            ['hr', 'Funmilayo Adeyemi', 'HR Manager', 'People & Culture Manager', 'female'],
            ['pm', 'Ibrahim Musa', 'Project Manager', 'Delivery Lead', 'male'],
            ['sales', 'Ngozi Eze', 'Sales Officer', 'Business Development Officer', 'female'],
            ['academy', 'Kemi Olatunji', 'Academy Administrator', 'Academy Coordinator', 'female'],
            ['instructor', 'Segun Afolabi', 'Instructor', 'Senior Instructor', 'male'],
            ['store', 'Halima Yusuf', 'Inventory Officer', 'Stores & Assets Officer', 'female'],
            ['staff1', 'Tunde Bakare', 'Staff', 'Backend Engineer', 'male'],
            ['staff2', 'Aisha Bello', 'Staff', 'Frontend Engineer', 'female'],
            ['staff3', 'Emeka Obi', 'Staff', 'Mobile Engineer', 'male'],
            ['staff4', 'Zainab Lawal', 'Staff', 'Product Designer', 'female'],
        ];
        foreach ($staff as [$k, $name, $role, $title, $gender]) {
            $this->user($k, $name, User::TYPE_STAFF, $role, ['job_title' => $title, 'gender' => $gender, 'phone_number' => '080' . random_int(10000000, 99999999)]);
        }

        $students = ['Chiamaka Nnadi', 'Oluwaseun Ogunleye', 'Fatima Abubakar', 'Chinedu Okeke', 'Blessing Udo', 'Yusuf Danjuma',
            'Temitope Akinola', 'Ifeoma Chukwu', 'Abdulrahman Sani', 'Precious Etim', 'Damilola Ajayi', 'Kelechi Onyema'];
        foreach ($students as $i => $name) {
            $this->user('student' . ($i + 1), $name, User::TYPE_STUDENT, 'Student');
        }

        foreach ([['client1', 'Bolaji Coker'], ['client2', 'Amina Garba']] as [$k, $name]) {
            $this->user($k, $name, User::TYPE_CLIENT, 'Client');
        }
    }

    // ── HR and payroll ──────────────────────────────────────────────

    protected function hr(): void
    {
        $year = $this->today->year;
        foreach ([["$year-01-01", "New Year's Day"], ["$year-05-01", "Workers' Day"], ["$year-06-12", 'Democracy Day'],
            ["$year-10-01", 'Independence Day'], ["$year-12-25", 'Christmas Day'], ["$year-12-26", 'Boxing Day']] as [$date, $name]) {
            Holiday::firstOrCreate(['date' => $date], ['name' => $name]);
        }

        $depts = [];
        foreach ([['Management', 'md'], ['Engineering', 'pm'], ['Academy', 'academy'], ['Finance & Admin', 'finance'], ['Sales', 'sales']] as [$name, $head]) {
            $depts[$name] = Department::create(['name' => $name, 'head_id' => $this->u[$head]->id]);
        }

        $units = BusinessUnit::pluck('id', 'name');
        $plan = [
            // key, department, monthly basic, housing, transport, manager key, unit
            ['md', 'Management', 950000, 300000, 100000, null, 'Qudroid Systems'],
            ['finance', 'Finance & Admin', 420000, 150000, 60000, 'md', 'Qudroid Systems'],
            ['hr', 'Finance & Admin', 400000, 140000, 60000, 'md', 'Qudroid Systems'],
            ['pm', 'Engineering', 520000, 180000, 70000, 'md', 'Qudroid Systems'],
            ['sales', 'Sales', 300000, 100000, 50000, 'md', 'Qudroid Systems'],
            ['academy', 'Academy', 350000, 120000, 50000, 'md', 'Coding Academy'],
            ['instructor', 'Academy', 380000, 120000, 50000, 'academy', 'Coding Academy'],
            ['store', 'Finance & Admin', 220000, 80000, 40000, 'finance', 'Qudroid Systems'],
            ['staff1', 'Engineering', 450000, 150000, 60000, 'pm', 'Qudroid Systems'],
            ['staff2', 'Engineering', 400000, 140000, 60000, 'pm', 'Qudroid Systems'],
            ['staff3', 'Engineering', 400000, 140000, 60000, 'pm', 'ViteSchool'],
            ['staff4', 'Engineering', 330000, 110000, 50000, 'pm', 'Qudroid Systems'],
        ];
        $banks = ['GTBank', 'Access Bank', 'Zenith Bank', 'First Bank', 'UBA', 'Kuda'];
        $emp = new EmployeeService;
        foreach ($plan as $i => [$k, $dept, $basic, $housing, $transport, $mgr, $unit]) {
            [$e] = $emp->create([
                'user_id' => $this->u[$k]->id, 'department_id' => $depts[$dept]->id, 'job_title' => $this->u[$k]->job_title,
                'employment_type' => 'full_time', 'start_date' => $this->today->copy()->subMonths(8 + $i * 2)->startOfMonth()->toDateString(),
                'manager_id' => $mgr ? $this->u[$mgr]->id : null,
                'basic_salary' => $basic, 'housing_allowance' => $housing, 'transport_allowance' => $transport, 'other_allowance' => 0,
                'annual_rent' => $housing * 12, 'pension_enrolled' => true, 'nhf_enrolled' => $i % 3 === 0,
                'bank_name' => $banks[$i % count($banks)], 'account_number' => (string) random_int(1000000000, 9999999999),
                'account_name' => $this->u[$k]->name, 'annual_leave_days' => 20,
                'next_of_kin_name' => 'Next of kin of ' . explode(' ', $this->u[$k]->name)[0], 'next_of_kin_phone' => '081' . random_int(10000000, 99999999),
            ]);
            if (isset($units[$unit])) $e->forceFill(['business_unit_id' => $units[$unit]])->save();
        }

        // Leave: one approved, one waiting.
        $leave = app(LeaveService::class);
        $annual = LeaveType::where('name', 'Annual leave')->first();
        $sick = LeaveType::where('name', 'Sick leave')->first();
        if ($annual) {
            $from = $this->weekday($this->today->copy()->subDays(30));
            $req = $this->at($from->copy()->subDays(10), fn () => $leave->request($this->employee('staff2'), [
                'leave_type_id' => $annual->id, 'starts_on' => $from->toDateString(), 'ends_on' => $from->copy()->addWeekdays(4)->toDateString(), 'reason' => 'Family visit to Enugu',
            ], $this->u['staff2']));
            $this->at($from->copy()->subDays(9), fn () => $leave->decide($req, $this->u['pm'], true, 'Enjoy the break.'));

            $soon = $this->today->copy()->addWeekdays(10);
            $leave->request($this->employee('staff1'), [
                'leave_type_id' => $annual->id, 'starts_on' => $soon->toDateString(), 'ends_on' => $soon->copy()->addWeekdays(2)->toDateString(), 'reason' => 'Wedding in Ibadan',
            ], $this->u['staff1']);
        }
        if ($sick) {
            $d = $this->weekday($this->today->copy()->subDays(12));
            $req = $this->at($d, fn () => $leave->request($this->employee('staff3'), ['leave_type_id' => $sick->id, 'starts_on' => $d->toDateString(), 'ends_on' => $d->toDateString(), 'reason' => 'Malaria'], $this->u['staff3']));
            $this->at($d, fn () => $leave->decide($req, $this->u['hr'], true));
        }

        // Last month's payroll, prepared, approved and paid from the bank account.
        $payroll = app(PayrollService::class);
        $month = $this->today->copy()->subMonthNoOverflow()->startOfMonth();
        $payDay = $month->copy()->endOfMonth()->setTime(11, 0);
        $bank = Account::where('code', '1010')->value('id');
        $this->at($payDay->copy()->subDays(5), function () use ($payroll, $month, $payDay, $bank) {
            $run = $payroll->start($month, $this->u['hr']);
            $run = $payroll->approve($run, $this->u['md']);
            Carbon::setTestNow($payDay);
            $payroll->pay($run, $this->u['finance'], $bank, $payDay->copy());
        });
    }

    protected function employee(string $key): Employee
    {
        return Employee::where('user_id', $this->u[$key]->id)->firstOrFail();
    }

    // ── Clients and deals ───────────────────────────────────────────

    /** @var array<string, Company> */
    protected array $companies = [];

    protected function crm(): void
    {
        $crm = app(CrmService::class);
        $list = [
            ['lekki', 'Lekki Gardens Realty', 'client', 'Real estate', 'Lagos', 'Bolaji Coker', 'Head of Operations', 'client1'],
            ['kano', 'Kano Agro Co-operative', 'client', 'Agriculture', 'Kano', 'Amina Garba', 'General Manager', 'client2'],
            ['ikeja', 'Ikeja Medics Hospital', 'prospect', 'Healthcare', 'Lagos', 'Dr. Femi Ayodele', 'Medical Director', null],
            ['abuja', 'Abuja Schools Board', 'prospect', 'Education', 'Abuja', 'Mrs. Hauwa Idris', 'Director of ICT', null],
            ['ph', 'Port Harcourt Logistics', 'client', 'Logistics', 'Port Harcourt', 'Tamuno Briggs', 'CTO', null],
            ['ibadan', 'Ibadan Fashion House', 'inactive', 'Retail', 'Ibadan', 'Yetunde Alabi', 'Owner', null],
        ];
        foreach ($list as $i => [$k, $name, $status, $industry, $city, $contact, $title, $userKey]) {
            $c = $crm->saveCompany(['name' => $name, 'status' => $status, 'industry' => $industry, 'city' => $city, 'country' => 'Nigeria',
                'email' => 'info@' . Str::slug($name) . '.example', 'phone' => '0' . random_int(700, 909) . random_int(1000000, 9999999),
                'website' => Str::slug($name) . '.example', 'source' => ['Referral', 'Website', 'LinkedIn', 'Event'][$i % 4],
                'owner_id' => $this->u[$i % 2 ? 'sales' : 'md']->id], $this->u['sales']);
            Contact::create(['company_id' => $c->id, 'name' => $contact, 'job_title' => $title, 'is_primary' => true,
                'email' => $userKey ? $this->u[$userKey]->email : Str::slug($contact, '.') . '@' . Str::slug($name) . '.example',
                'phone' => '080' . random_int(10000000, 99999999), 'user_id' => $userKey ? $this->u[$userKey]->id : null]);
            $this->companies[$k] = $c;
        }

        $deals = [
            ['ikeja', 'Patient records system', 18500000, 'proposal'],
            ['abuja', 'Teacher training: 40 schools', 12000000, 'qualified'],
            ['lekki', 'Buyer portal phase 2', 7500000, 'negotiation'],
            ['ph', 'Fleet tracking app', 9800000, 'contacted'],
            ['kano', 'Farmer payments app', 6400000, 'new'],
        ];
        foreach ($deals as [$k, $title, $value, $stage]) {
            $crm->saveDeal(['title' => $title, 'company_id' => $this->companies[$k]->id, 'contact_id' => $this->companies[$k]->contacts()->value('id'),
                'value' => $value, 'stage' => $stage, 'expected_close_on' => $this->today->copy()->addDays(random_int(14, 90))->toDateString(),
                'source' => 'Referral', 'owner_id' => $this->u['sales']->id], $this->u['sales']);
        }
        $won = $crm->saveDeal(['title' => 'Buyer portal', 'company_id' => $this->companies['lekki']->id, 'value' => 14000000, 'stage' => 'negotiation'], $this->u['sales']);
        $crm->win($won, $this->u['md']);
        $lost = $crm->saveDeal(['title' => 'E-commerce site', 'company_id' => $this->companies['ibadan']->id, 'value' => 3200000, 'stage' => 'proposal'], $this->u['sales']);
        $crm->lose($lost, $this->u['sales'], 'Went with a cheaper freelancer');

        $crm->log($this->companies['ikeja'], ['type' => 'call', 'body' => 'Walked Dr. Ayodele through the proposal. They want a pilot in the outpatient wing first.'], $this->u['sales']);
        $crm->log($this->companies['abuja'], ['type' => 'meeting', 'body' => 'Demo of the academy LMS to the ICT team.'], $this->u['md']);
        $crm->log($this->companies['ph'], ['type' => 'follow_up', 'body' => 'Send the fleet tracking estimate', 'due_at' => $this->day(3, '12:00')->toDateTimeString(), 'assigned_to' => $this->u['sales']->id], $this->u['sales']);
    }

    // ── Projects ────────────────────────────────────────────────────

    /** @var array<string, \App\Models\Projects\Project> */
    protected array $projects = [];

    protected function projects(): void
    {
        $svc = app(ProjectService::class);
        $team = ['staff1', 'staff2', 'staff3', 'staff4'];
        $plans = [
            'LGR' => ['Lekki Gardens buyer portal', 'lekki', 'client1', 'active', -60, 45, 14000000, '#405189', [
                ['Discovery and design', -40, true, [['Interview the sales team', 'done'], ['Wireframes for the buyer dashboard', 'done'], ['Design system in Figma', 'done']]],
                ['Buyer accounts and payments', 10, false, [['Paystack instalment plans', 'in_progress'], ['Buyer sign-up and KYC', 'review'], ['Payment receipts by e-mail', 'todo'], ['Allocation letters (PDF)', 'todo']]],
                ['Launch', 45, false, [['Load testing', 'todo'], ['Staff training session', 'todo']]],
            ]],
            'KAC' => ['Kano farmer payments app', 'kano', 'client2', 'planning', 7, 120, 6400000, '#0ab39c', [
                ['Field research', 30, false, [['Visit three co-operative centres', 'todo'], ['USSD vs app survey', 'todo']]],
            ]],
            'VIT' => ['ViteSchool mobile app', null, null, 'active', -90, 30, null, '#f7b84b', [
                ['Beta', -10, true, [['Offline lessons', 'done'], ['Push notifications', 'done']]],
                ['Store release', 30, false, [['App Store screenshots', 'in_progress'], ['Fix Android login crash', 'in_progress'], ['Play Store listing', 'todo']]],
            ]],
            'PHL' => ['Port Harcourt fleet dashboard', 'ph', null, 'completed', -150, -20, 5200000, '#299cdb', [
                ['Delivery', -25, true, [['Live map of trucks', 'done'], ['Fuel reports', 'done']]],
            ]],
        ];
        $n = 0;
        foreach ($plans as $code => [$name, $company, $client, $status, $start, $due, $budget, $color, $milestones]) {
            $p = $this->at($this->day($start < 0 ? $start : -3), fn () => $svc->create([
                'code' => $code, 'name' => $name, 'description' => "Sample project: $name.", 'company_id' => $company ? $this->companies[$company]->id : null,
                'client_name' => $company ? $this->companies[$company]->name : null, 'client_id' => $client ? $this->u[$client]->id : null,
                'status' => $status, 'priority' => $code === 'LGR' ? 'high' : 'normal', 'starts_on' => $this->day($start)->toDateString(),
                'due_on' => $this->day($due)->toDateString(), 'budget' => $budget, 'color' => $color, 'manager_id' => $this->u['pm']->id,
            ], $this->u['pm']));
            if ($code === 'VIT' && ($unit = BusinessUnit::where('name', 'ViteSchool')->value('id'))) $p->forceFill(['business_unit_id' => $unit])->save();
            foreach ($team as $k) $svc->setMember($p, $this->u[$k]->id, 'member');
            if ($status === 'completed') $p->forceFill(['completed_at' => $this->day($due)])->save();

            foreach ($milestones as $mi => [$title, $dueIn, $done, $tasks]) {
                $m = $svc->addMilestone($p, ['title' => $title, 'due_on' => $this->day($dueIn)->toDateString()]);
                if ($done) $m->forceFill(['completed_at' => $this->day(min($dueIn, -1))])->save();
                foreach ($tasks as [$taskTitle, $taskStatus]) {
                    $who = $team[$n++ % count($team)];
                    $task = $svc->createTask($p, ['title' => $taskTitle, 'milestone_id' => $m->id, 'status' => $taskStatus, 'assignee_id' => $this->u[$who]->id,
                        'priority' => ['normal', 'high', 'low', 'urgent'][$n % 4], 'due_on' => $this->day($dueIn - 3)->toDateString(), 'estimate_hours' => [4, 8, 12, 16][$n % 4],
                        'client_visible' => $n % 2 === 0], $this->u['pm']);
                    if ($taskStatus !== 'todo') {
                        $svc->comment($task, $this->u[$who], ['Started on this.', 'Pushed a first version for review.', 'Done, see the staging link.'][$n % 3], false);
                        foreach ([1, 2] as $back) {
                            $date = $this->weekday($this->today->copy()->subDays($back * 2 + $n % 5));
                            if ($date->lt($p->starts_on)) continue;
                            $svc->logTime($p, $this->u[$who], ['task_id' => $task->id, 'date' => $date->toDateString(), 'hours' => [1.5, 2, 3, 4][($n + $back) % 4],
                                'note' => 'Work on ' . Str::lower($taskTitle), 'billable' => $p->company_id !== null]);
                        }
                    }
                }
            }
            $this->projects[$code] = $p;
        }
    }

    // ── Finance ─────────────────────────────────────────────────────

    protected function finance(): void
    {
        $inv = app(InvoiceService::class);
        $pay = app(PaymentService::class);
        $fo = $this->u['finance'];

        $a = $this->at($this->day(-50), fn () => $inv->issue($this->u['client1'], 'Lekki Gardens buyer portal: discovery and design', [
            ['description' => 'Discovery workshops (3 days)', 'quantity' => 3, 'unit_price' => 450000],
            ['description' => 'UX and UI design', 'quantity' => 1, 'unit_price' => 2800000],
        ], $fo, ['due_date' => $this->day(-36)->toDateString(), 'notes' => 'Thank you for your business.']));
        $this->at($this->day(-40, '14:00'), fn () => $pay->recordManual($a, ['method' => 'bank_transfer', 'amount' => $a->total, 'reference' => 'GTB-LGR-' . random_int(100000, 999999)], $fo));

        $b = $this->at($this->day(-12), fn () => $inv->issue($this->u['client1'], 'Lekki Gardens buyer portal: milestone 2 (50%)', [
            ['description' => 'Buyer accounts and payments, first half', 'quantity' => 1, 'unit_price' => 4200000],
        ], $fo, ['due_date' => $this->day(2)->toDateString()]));
        $this->at($this->day(-5, '11:30'), fn () => $pay->recordManual($b, ['method' => 'bank_transfer', 'amount' => 2000000, 'reference' => 'GTB-LGR-' . random_int(100000, 999999)], $fo));

        $this->at($this->day(-45), fn () => $inv->issue($this->u['client2'], 'Farmer payments app: research deposit', [
            ['description' => 'Field research and requirements', 'quantity' => 1, 'unit_price' => 1500000],
        ], $fo, ['due_date' => $this->day(-15)->toDateString()]));   // overdue

        // Expenses: paid, approved and waiting.
        $exp = app(ExpenseService::class);
        $acc = fn (string $code) => Account::where('code', $code)->value('id');
        $bank = $acc('1010');
        $rows = [
            [-40, 'Ikeja Property Managers', 'Office rent, quarter', '5100', 2400000, 'paid'],
            [-28, 'Ikeja Electric', 'Electricity, last month', '5200', 185000, 'paid'],
            [-26, 'Total Energies Allen', 'Diesel for the generator', '5200', 240000, 'paid'],
            [-20, 'Spectranet', 'Office internet, 3 months', '5300', 135000, 'paid'],
            [-14, 'Google Workspace', 'E-mail and storage', '5300', 96000, 'paid'],
            [-9, 'Bolt Business', 'Client visits, Lekki', '5600', 48500, 'approved'],
            [-6, 'Jumia', 'Printer toner and paper', '5800', 62000, 'approved'],
            [-2, 'Instagram ads', 'Academy cohort promotion', '5500', 150000, 'submitted'],
        ];
        foreach ($rows as [$d, $payee, $desc, $code, $amount, $state]) {
            $e = $this->at($this->day($d), fn () => $exp->submit(['date' => $this->day($d)->toDateString(), 'payee' => $payee, 'description' => $desc,
                'account_id' => $acc($code), 'amount' => $amount], $this->u[$d % 2 ? 'store' : 'finance']));
            if ($state === 'submitted') continue;
            $this->at($this->day($d + 1), fn () => $exp->approve($e, $this->u['md']));
            if ($state === 'paid') $this->at($this->day($d + 2), fn () => $exp->pay($e, $fo, $bank, 'TRF-' . random_int(100000, 999999), $this->day($d + 2)));
        }
    }

    // ── Academy ─────────────────────────────────────────────────────

    protected function academy(): void
    {
        $unit = BusinessUnit::where('name', 'Coding Academy')->value('id');
        $prog = Programme::create(['name' => 'Software Engineering', 'slug' => 'software-engineering', 'description' => 'From first web page to a job-ready portfolio.', 'position' => 1, 'is_active' => true]);
        $prog2 = Programme::create(['name' => 'Data and Design', 'slug' => 'data-and-design', 'description' => 'Analysis, dashboards and product design.', 'position' => 2, 'is_active' => true]);

        $courses = [
            ['WEB101', 'Web Development Foundations', $prog, 'beginner', 12, 180000, ['HTML and the web', 'CSS and layout', 'JavaScript basics']],
            ['LAR201', 'Backend with Laravel', $prog, 'intermediate', 10, 250000, ['PHP refresher', 'Laravel routing and Blade', 'Databases with Eloquent']],
            ['DAT101', 'Data Analysis with Excel and Power BI', $prog2, 'beginner', 8, 150000, ['Spreadsheets that work', 'Power BI dashboards']],
            ['UIX101', 'Product Design with Figma', $prog2, 'beginner', 8, 160000, ['Design basics', 'Prototyping']],
        ];
        $made = [];
        foreach ($courses as [$code, $title, $p, $level, $weeks, $fee, $sections]) {
            $c = Course::create(['programme_id' => $p->id, 'code' => $code, 'title' => $title, 'slug' => Str::slug($title), 'level' => $level,
                'summary' => "A practical $weeks-week course.", 'description' => "In $title you build real projects every week with an instructor and a small class.",
                'delivery_mode' => 'hybrid', 'duration_weeks' => $weeks, 'fee' => $fee, 'currency' => 'NGN', 'installments_allowed' => true,
                'min_deposit' => $fee / 2, 'pass_grade' => 50, 'certificate_enabled' => true, 'status' => 'published', 'created_by' => $this->u['academy']->id]);
            if ($unit) $c->forceFill(['business_unit_id' => $unit])->save();
            foreach ($sections as $si => $st) {
                $s = $c->sections()->create(['title' => $st, 'position' => $si]);
                foreach (['Introduction', 'Worked example', 'Practice'] as $li => $lt) {
                    $c->lessons()->create(['section_id' => $s->id, 'title' => "$st: $lt", 'type' => 'text', 'position' => $si * 10 + $li,
                        'body' => "<p>$lt for <strong>$st</strong>. Read through, then try the exercise at the end.</p>", 'duration_minutes' => 15 + 5 * $li,
                        'is_preview' => $si === 0 && $li === 0, 'is_published' => true, 'created_by' => $this->u['instructor']->id]);
                }
            }
            $made[$code] = $c;
        }
        $quiz = $made['WEB101']->assessments()->create(['type' => 'quiz', 'title' => 'HTML check', 'weight' => 30, 'pass_mark' => 50, 'max_attempts' => 2,
            'is_published' => true, 'position' => 0, 'created_by' => $this->u['instructor']->id]);
        $quiz->questions()->create(['type' => 'single', 'question' => 'Which tag makes a link?', 'options' => ['<a>', '<p>', '<div>'], 'correct' => [0], 'points' => 1, 'position' => 0]);
        $quiz->questions()->create(['type' => 'multiple', 'question' => 'Which are block elements?', 'options' => ['div', 'span', 'p', 'a'], 'correct' => [0, 2], 'points' => 2, 'position' => 1]);
        $quiz->questions()->create(['type' => 'short_answer', 'question' => 'What does HTML stand for?', 'accepted_answers' => ['HyperText Markup Language'], 'points' => 1, 'position' => 2]);
        $made['WEB101']->assessments()->create(['type' => 'assignment', 'title' => 'Build your portfolio page', 'instructions' => 'One page about you, with a photo, three projects and a contact link.',
            'weight' => 70, 'pass_mark' => 50, 'max_score' => 100, 'due_at' => $this->day(14, '23:59'), 'is_published' => true, 'position' => 1, 'created_by' => $this->u['instructor']->id]);

        // A running cohort, and one open for registration.
        $running = Cohort::create(['course_id' => $made['WEB101']->id, 'code' => 'WEB101-' . $this->today->format('y') . 'A', 'name' => 'Web Foundations, ' . $this->today->copy()->subWeeks(4)->format('F Y'),
            'starts_on' => $this->today->copy()->subWeeks(4)->toDateString(), 'ends_on' => $this->today->copy()->addWeeks(8)->toDateString(),
            'capacity' => 25, 'delivery_mode' => 'hybrid', 'instructor_id' => $this->u['instructor']->id, 'schedule' => 'Tue and Thu, 10am to 1pm', 'status' => 'running']);
        $open = Cohort::create(['course_id' => $made['LAR201']->id, 'code' => 'LAR201-' . $this->today->format('y') . 'A', 'name' => 'Laravel, ' . $this->today->copy()->addWeeks(3)->format('F Y'),
            'starts_on' => $this->today->copy()->addWeeks(3)->toDateString(), 'ends_on' => $this->today->copy()->addWeeks(13)->toDateString(),
            'registration_closes_on' => $this->today->copy()->addWeeks(3)->toDateString(), 'capacity' => 20, 'delivery_mode' => 'online',
            'instructor_id' => $this->u['instructor']->id, 'schedule' => 'Mon and Wed, 6pm to 8pm', 'status' => 'open']);

        $enrol = app(EnrollmentService::class);
        $pay = app(PaymentService::class);
        $start = $this->today->copy()->subWeeks(5);
        for ($i = 1; $i <= 12; $i++) {
            $user = $this->u['student' . $i];
            $student = Student::create(['user_id' => $user->id, 'student_no' => Student::nextNumber(), 'status' => 'admitted', 'education_level' => ['OND', 'HND', 'BSc', 'SSCE'][$i % 4],
                'institution' => ['UNILAG', 'Yaba College of Technology', 'OAU Ife', 'ABU Zaria', 'UNN'][$i % 5], 'referral_source' => ['Instagram', 'A friend', 'Google', 'Twitter'][$i % 4],
                'admitted_at' => $start, 'admitted_by' => $this->u['academy']->id]);
            $cohort = $i <= 9 ? $running : $open;
            $enrolment = $this->at($i <= 9 ? $start->copy()->addDays($i) : $this->day(-$i), function () use ($enrol, $student, $cohort, $i) {
                $cohort->forceFill(['status' => 'open'])->save();
                $e = $enrol->enroll($student, $cohort, $this->u['academy'], ['discount_amount' => $i % 4 === 0 ? 20000 : 0]);
                $cohort->forceFill(['status' => $cohort->starts_on->isFuture() ? 'open' : 'running'])->save();

                return $e;
            });
            $invoice = $enrolment->invoices()->latest('id')->first() ?? \App\Models\Finance\Invoice::where('enrollment_id', $enrolment->id)->first();
            if (!$invoice) continue;
            // Most pay in full, a few pay the deposit, two haven't paid yet.
            $amount = match (true) { $i % 5 === 0 => null, $i % 3 === 0 => $invoice->total / 2, default => $invoice->total };
            if ($amount) {
                $this->at($i <= 9 ? $start->copy()->addDays($i + 1) : $this->day(-$i + 1), fn () => $pay->recordManual($invoice,
                    ['method' => ['bank_transfer', 'pos', 'cash'][$i % 3], 'amount' => $amount, 'reference' => 'ACD-' . $i . '-' . random_int(10000, 99999)], $this->u['finance']));
            }
        }

        // Classes twice a week; registers taken for the ones that have happened.
        $att = app(AttendanceService::class);
        $att->schedule($running, $running->starts_on->copy(), $running->ends_on->copy(), [Carbon::TUESDAY, Carbon::THURSDAY], '10:00', '13:00', null);
        $topics = ['Welcome and setup', 'How the web works', 'HTML structure', 'Forms', 'CSS selectors', 'Flexbox', 'Grid', 'Responsive design', 'JavaScript variables', 'Functions', 'The DOM', 'Events'];
        foreach ($running->sessions()->orderBy('date')->get() as $si => $session) {
            $session->update(['topic' => $topics[$si % count($topics)]]);
            if ($session->date->gte($this->today)) continue;
            $marks = [];
            foreach ($att->roster($session) as $j => $e) {
                $marks[$e->id] = ['status' => ($j + $si) % 11 === 0 ? 'absent' : (($j + $si) % 7 === 0 ? 'late' : 'present')];
            }
            if ($marks) $this->at($session->date->copy()->setTime(13, 5), fn () => $att->takeRegister($session, $marks, $this->u['instructor']));
        }
        $att->recomputeCohort($running);
    }

    // ── Inventory and assets ────────────────────────────────────────

    protected function inventory(): void
    {
        $store = $this->u['store'];
        $main = Location::first() ?? Location::create(['name' => 'Main store', 'is_active' => true]);
        $lab = Location::create(['name' => 'Academy lab, Yaba', 'description' => 'Training room store', 'is_active' => true]);
        $sup = [];
        foreach ([['slot', 'Slot Systems Ltd', 'Mr. Nnamdi Ude'], ['computer', 'Computer Village Traders', 'Alhaji Musa Bello'], ['office', 'Officemate Supplies', 'Mrs. Joy Okon']] as [$k, $name, $contact]) {
            $sup[$k] = Supplier::create(['name' => $name, 'contact_name' => $contact, 'email' => 'sales@' . Str::slug($name) . '.example', 'phone' => '080' . random_int(10000000, 99999999),
                'address' => 'Ikeja, Lagos', 'is_active' => true]);
        }
        $cat = fn (string $kind) => Category::where('kind', $kind)->value('id') ?? Category::value('id');

        $stock = app(StockService::class);
        $items = [];
        foreach ([['PAP-A4', 'A4 paper (ream)', 'ream', 10, 4500, 'office', 60, $main], ['TNR-85A', 'Printer toner 85A', 'piece', 2, 28000, 'office', 6, $main],
            ['HDMI-2M', 'HDMI cable, 2m', 'piece', 5, 3500, 'computer', 25, $lab], ['MKB-01', 'Mouse and keyboard set', 'set', 5, 12000, 'computer', 3, $lab],
            ['WTR-75', 'Bottled water (pack)', 'pack', 10, 2200, 'office', 40, $main]] as [$sku, $name, $unit, $reorder, $cost, $s, $qty, $loc]) {
            $item = Item::create(['sku' => $sku, 'name' => $name, 'unit' => $unit, 'reorder_level' => $reorder, 'supplier_id' => $sup[$s]->id,
                'category_id' => $cat('consumable'), 'is_active' => true]);
            $this->at($this->day(-30), fn () => $stock->receive($item, $loc, $qty, $cost, $store, ['reference' => 'Opening stock']));
            $items[$sku] = $item;
        }
        $this->at($this->day(-8), fn () => $stock->issue($items['PAP-A4']->fresh(), $main, 12, $store, ['issued_to' => $this->u['academy']->id, 'note' => 'Class handouts']));
        $this->at($this->day(-3), fn () => $stock->issue($items['WTR-75']->fresh(), $main, 30, $store, ['note' => 'Client workshop']));

        $po = app(PurchaseOrderService::class);
        $order = $po->save(['supplier_id' => $sup['computer']->id, 'location_id' => $lab->id, 'expected_on' => $this->day(7)->toDateString(), 'notes' => 'For the new Laravel cohort'],
            [['item_id' => $items['MKB-01']->id, 'quantity' => 15, 'unit_cost' => 11500], ['item_id' => $items['HDMI-2M']->id, 'quantity' => 10, 'unit_cost' => 3300]], $store);
        $po->place($order);

        $assets = app(AssetService::class);
        $laptops = [['staff1', 'MacBook Pro 14"', 'Apple M3 Pro', 2950000], ['staff2', 'MacBook Air 13"', 'Apple M3', 1650000], ['staff3', 'ThinkPad X1 Carbon', 'Lenovo Gen 11', 1850000],
            ['staff4', 'MacBook Air 15"', 'Apple M3', 1950000], ['pm', 'Dell XPS 13', 'Dell 9340', 1700000], [null, 'HP ProBook 450', 'HP G10', 950000], [null, 'HP ProBook 450', 'HP G10', 950000]];
        foreach ($laptops as $i => [$who, $name, $model, $cost]) {
            $a = $this->at($this->day(-200 + $i), fn () => $assets->create(['name' => $name, 'make_model' => $model, 'serial_number' => strtoupper(Str::random(10)),
                'category_id' => $cat('asset'), 'supplier_id' => $sup['slot']->id, 'purchase_date' => $this->day(-200 + $i)->toDateString(),
                'purchase_cost' => $cost, 'warranty_until' => $this->day(165 + $i)->toDateString(), 'condition' => 'good', 'location_id' => $main->id], $store));
            if ($who) $this->at($this->day(-190 + $i), fn () => $assets->assign($a, $this->u[$who], $store, 'Work laptop'));
        }
        $gen = $assets->create(['name' => 'Generator 15kVA', 'make_model' => 'Mikano MP15', 'category_id' => $cat('asset'), 'purchase_cost' => 4800000,
            'purchase_date' => $this->day(-400)->toDateString(), 'condition' => 'fair', 'location_id' => $main->id], $store);
        $assets->repair($gen, $store, 'Service due: oil and filters');
    }

    // ── Meetings ────────────────────────────────────────────────────

    protected function meetings(): void
    {
        $board = Room::first() ?? Room::create(['name' => 'Boardroom', 'capacity' => 10, 'is_active' => true]);
        $huddle = Room::create(['name' => 'Huddle room', 'capacity' => 4, 'description' => 'Screen and whiteboard', 'is_active' => true]);
        $svc = app(MeetingService::class);
        $all = collect(['md', 'finance', 'hr', 'pm', 'sales', 'academy', 'instructor', 'store', 'staff1', 'staff2', 'staff3', 'staff4'])->map(fn ($k) => $this->u[$k]->id)->all();
        $eng = collect(['staff1', 'staff2', 'staff3', 'staff4'])->map(fn ($k) => $this->u[$k]->id)->all();

        // Past meetings, with minutes.
        foreach ([[-7, 'Monday all-hands', 'md', $all, $board, 'Q4 targets agreed. Academy enrolment is ahead of plan. Payroll moves to the 25th from next month.'],
            [-3, 'Lekki Gardens sprint review', 'pm', $eng, $huddle, 'Instalment plans demoed to the client. KYC needs one more round of fixes.']] as [$d, $title, $org, $people, $room, $minutes]) {
            $start = $this->weekday($this->day($d, '10:00'));
            $m = Meeting::create(['title' => $title, 'starts_at' => $start, 'ends_at' => $start->copy()->addHour(), 'room_id' => $room->id,
                'organizer_id' => $this->u[$org]->id, 'status' => 'scheduled', 'remind_minutes' => 15, 'reminded_at' => $start->copy()->subMinutes(15), 'minutes' => $minutes,
                'project_id' => $title === 'Lekki Gardens sprint review' ? $this->projects['LGR']->id : null]);
            foreach (array_unique(array_merge([$this->u[$org]->id], $people)) as $id) $m->attendees()->create(['user_id' => $id, 'response' => 'accepted']);
        }

        // Coming up.
        $svc->schedule(['title' => 'Monday all-hands', 'agenda' => "1. Last week\n2. This week\n3. Shout-outs", 'starts_at' => $this->nextWeekday(1, '09:30'),
            'ends_at' => $this->nextWeekday(1, '10:15'), 'room_id' => $board->id], $all, $this->u['md']);
        $svc->schedule(['title' => 'Ikeja Medics: proposal walk-through', 'starts_at' => $this->nextWeekday(2, '14:00'), 'ends_at' => $this->nextWeekday(2, '15:00'),
            'location' => 'Ikeja Medics Hospital, Allen Avenue', 'company_id' => $this->companies['ikeja']->id, 'guests' => "Dr. Femi Ayodele <femi@ikeja-medics-hospital.example>"],
            [$this->u['sales']->id, $this->u['pm']->id], $this->u['md']);
        $svc->schedule(['title' => 'ViteSchool release planning', 'starts_at' => $this->nextWeekday(3, '11:00'), 'ends_at' => $this->nextWeekday(3, '12:00'),
            'room_id' => $huddle->id, 'project_id' => $this->projects['VIT']->id, 'online_url' => 'https://meet.google.com/abc-defg-hij'], $eng, $this->u['pm']);
    }

    protected function nextWeekday(int $nth, string $time): string
    {
        $d = $this->today->copy();
        for ($i = 0; $i < $nth; $i++) {
            do { $d->addDay(); } while ($d->isWeekend());
        }

        return $d->setTimeFromTimeString($time)->toDateTimeString();
    }

    // ── Chat ────────────────────────────────────────────────────────

    protected function chat(): void
    {
        $c = app(ConnectService::class);
        $general = $c->channel($this->u['md'], 'general', 'Company-wide news and chat');
        $c->add($general, $this->u['md'], collect($this->u)->filter(fn ($u) => $u->isStaff())->pluck('id')->all());
        $eng = $c->channel($this->u['pm'], 'engineering', 'Builds, releases and code reviews');
        $c->add($eng, $this->u['pm'], collect(['staff1', 'staff2', 'staff3', 'staff4'])->map(fn ($k) => $this->u[$k]->id)->all());

        $lines = [
            [$general, 'md', 'Good morning team! Reminder that Monday all-hands moves to 9:30 from next week.', -2],
            [$general, 'hr', 'Payslips for last month are up under HR → My payslips. Shout if anything looks off.', -2],
            [$general, 'academy', 'The Laravel cohort has opened for registration. Please share the link with friends.', -1],
            [$general, 'staff4', 'Jollof Friday is back this week 🍛', -1],
            [$eng, 'staff1', 'Paystack instalment plans are on staging. @Aisha Bello can you check the receipts page?', -1],
            [$eng, 'staff2', 'On it. The amount shows without the naira sign on mobile, fixing.', -1],
            [$eng, 'staff3', 'Android build 1.4.2 is in internal testing.', 0],
            [$eng, 'pm', 'Nice. Let\'s aim to cut the store release on Thursday.', 0],
        ];
        foreach ($lines as [$conv, $who, $text, $d]) {
            $this->at($this->day($d, sprintf('%02d:%02d', 9 + random_int(0, 7), random_int(0, 59))), fn () => $c->send($conv, $this->u[$who], $text));
        }
        $poll = $c->sendPoll($general, $this->u['hr'], 'End-of-year party: which Saturday?', ['13 December', '20 December'], false);
        foreach (['staff1', 'staff2', 'staff3', 'sales', 'finance'] as $i => $k) $c->vote($poll, $this->u[$k], [$i % 2]);

        $dm = $c->direct($this->u['pm'], $this->u['staff1']);
        $c->send($dm, $this->u['pm'], 'Can you join the Lekki Gardens call tomorrow at 11?');
        $c->send($dm, $this->u['staff1'], 'Yes, I\'ll be there.');

        if (isset($this->projects['LGR'])) {
            $p = $c->forProject($this->projects['LGR'], $this->u['pm']);
            $c->send($p, $this->u['pm'], 'Client signed off the design. Milestone 2 starts now.');
        }
    }

    // ── QR check-in ─────────────────────────────────────────────────

    protected function checkin(): void
    {
        $svc = app(CheckinService::class);
        $hq = Place::create(['name' => 'Qudroid HQ, Yaba', 'address' => '12 Herbert Macaulay Way, Yaba, Lagos', 'lat' => 6.5095, 'lng' => 3.3711, 'radius_m' => 150,
            'work_starts' => '09:00', 'work_ends' => '17:00', 'grace_minutes' => 15, 'workdays' => [1, 2, 3, 4, 5], 'require_location' => false, 'is_active' => true]);
        Place::create(['name' => 'Academy lab, Yaba', 'address' => '3 Commercial Avenue, Yaba, Lagos', 'radius_m' => 100, 'work_starts' => '08:30', 'work_ends' => '16:30',
            'grace_minutes' => 10, 'workdays' => [1, 2, 3, 4, 5, 6], 'is_active' => true]);

        // Ten working days of clock-ins at HQ.
        $people = ['md', 'finance', 'hr', 'pm', 'sales', 'store', 'staff1', 'staff2', 'staff3', 'staff4'];
        for ($back = 14; $back >= 1; $back--) {
            $d = $this->today->copy()->subDays($back);
            if ($d->isWeekend() || Holiday::whereDate('date', $d)->exists()) continue;
            $day = $this->at($d->copy()->setTime(7, 0), fn () => $svc->staffDay($hq, $d->copy()));
            foreach ($people as $i => $k) {
                if (($i + $back) % 9 === 0) continue;   // away that day
                $in = $d->copy()->setTime(8, 30)->addMinutes((($i * 7 + $back * 3) % 55));
                $late = $in->gt($d->copy()->setTime(9, 15));
                Record::create(['event_id' => $day->id, 'user_id' => $this->u[$k]->id, 'status' => $late ? 'late' : 'present', 'method' => $i % 3 ? 'screen' : 'badge',
                    'checked_in_at' => $in, 'checked_out_at' => $d->copy()->setTime(17, 0)->addMinutes(($i * 11 + $back) % 70), 'scanned_by' => $i % 3 ? null : $this->u['hr']->id]);
            }
        }

        // A conference next month with public sign-up.
        $start = $this->today->copy()->addWeeks(3)->next(Carbon::SATURDAY)->setTime(9, 0);
        $event = CheckinEvent::create(['kind' => 'event', 'title' => 'Qudroid Tech Summit ' . $start->year, 'description' => 'A day of talks and workshops on building software in Nigeria.',
            'location' => 'Landmark Centre, Victoria Island, Lagos', 'starts_at' => $start, 'ends_at' => $start->copy()->setTime(17, 0), 'opens_minutes' => 90, 'late_minutes' => 60,
            'audience' => 'registered', 'allow_screen' => true, 'allow_badge' => true, 'public_registration' => true, 'slug' => 'tech-summit-' . $start->year,
            'capacity' => 300, 'registration_closes_at' => $start->copy()->subDay(), 'status' => 'open', 'secret' => Str::random(40), 'created_by' => $this->u['md']->id]);
        $event->people()->attach([$this->u['sales']->id => ['role' => 'host'], $this->u['academy']->id => ['role' => 'host']]);
        $guests = [['Olumide Adebayo', 'Paystack'], ['Chioma Obi', 'Flutterwave'], ['Musa Ibrahim', 'Andela'], ['Grace Effiong', 'Freelance'], ['Uche Nwankwo', 'UNILAG'],
            ['Halima Sule', 'Kano State ICT'], ['Tobi Lawson', 'Moniepoint'], ['Efe Okoro', 'Student']];
        foreach ($guests as [$name, $org]) {
            $svc->register($event, ['name' => $name, 'email' => Str::slug($name, '.') . '@' . self::DOMAIN, 'organisation' => $org]);
        }
    }

    // ── Drive ───────────────────────────────────────────────────────

    protected function drive(): void
    {
        foreach (['Company policies', 'Brand assets', 'Contracts'] as $name) {
            Folder::create(['name' => $name, 'owner_id' => $this->u['md']->id]);
        }
        foreach ($this->projects as $p) {
            foreach (['Designs', 'Client documents'] as $name) Folder::create(['name' => $name, 'owner_id' => $this->u['pm']->id, 'project_id' => $p->id]);
        }
    }
}

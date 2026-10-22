<?php

use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\User;
use App\Services\Academy\EnrollmentService;
use App\Services\Finance\PaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();

    $course = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 150000, 'currency' => 'NGN', 'status' => 'published']);
    $cohort = $course->cohorts()->create(['code' => 'C1', 'name' => 'Cohort 1', 'status' => 'open']);
    $this->me = User::factory()->create(['user_type' => 'student', 'name' => 'Ada Obi']);
    $this->me->assignRole('Student');
    $this->me->forceFill(['must_change_password' => false])->save();
    $student = Student::create(['user_id' => $this->me->id, 'student_no' => Student::nextNumber(), 'status' => 'admitted']);
    $this->invoice = app(EnrollmentService::class)->enroll($student, $cohort, $this->admin)->invoices()->first();
    $this->payment = app(PaymentService::class)->recordManual($this->invoice, ['amount' => 50000, 'method' => 'bank_transfer', 'reference' => 'TRF-1'], $this->admin);
});

test('staff and the customer download invoice and receipt PDFs', function () {
    $r = $this->actingAs($this->admin)->get(route('finance.invoices.pdf', $this->invoice));
    $r->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($r->headers->get('content-disposition'))->toContain('Invoice-' . $this->invoice->number . '.pdf')
        ->and(substr($r->getContent(), 0, 4))->toBe('%PDF');

    $this->actingAs($this->admin)->get(route('finance.payments.pdf', $this->payment))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($this->me)->get(route('billing.invoice.pdf', [$this->invoice, 'view' => 1]))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($this->me)->get(route('billing.receipt.pdf', $this->payment))->assertOk();
    $this->actingAs($this->me)->get(route('billing.invoice', $this->invoice))->assertSee('Download PDF');
});

test('other people cannot download someone else\'s documents', function () {
    $other = User::factory()->create();
    $other->assignRole('Student');
    $other->forceFill(['must_change_password' => false])->save();
    $this->actingAs($other)->get(route('billing.invoice.pdf', $this->invoice))->assertNotFound();
    $this->actingAs($other)->get(route('billing.receipt.pdf', $this->payment))->assertNotFound();
    $this->actingAs($other)->get(route('finance.invoices.pdf', $this->invoice))->assertForbidden();
});

test('the documents say what they contain', function () {
    $html = view('pdf.invoice', ['invoice' => $this->invoice->fresh()->load('items', 'user')])->render();
    expect($html)->toContain($this->invoice->number)->toContain('Ada Obi')->toContain('Balance due');

    $html = view('pdf.receipt', ['payment' => $this->payment->fresh()->load('user', 'allocations.invoice')])->render();
    expect($html)->toContain($this->payment->number)->toContain('TRF-1')->toContain('Thank you');
});

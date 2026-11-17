<?php

use App\Mail\AcademyMail;
use App\Mail\ReceiptMail;
use App\Models\Academy\Application;
use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Services\Finance\ReceiptMailer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Notification::fake();
    Mail::fake();
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();

    $this->web = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 150000, 'currency' => 'NGN', 'status' => 'published']);
    $this->webClass = $this->web->cohorts()->create(['code' => 'W1', 'name' => 'December class', 'status' => 'open', 'starts_on' => today()->addMonth()]);
    $this->webLate = $this->web->cohorts()->create(['code' => 'W2', 'name' => 'January class', 'status' => 'open', 'starts_on' => today()->addMonths(2)]);
    $this->py = Course::create(['code' => 'PY101', 'title' => 'Python Basics', 'fee' => 100000, 'currency' => 'NGN', 'status' => 'published']);
    $this->pyClass = $this->py->cohorts()->create(['code' => 'P1', 'name' => 'Weekend class', 'status' => 'open', 'capacity' => 5]);

    $this->person = [
        'name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone_number' => '08030000000', 'consent' => 1,
        'experience_level' => 'none', 'has_laptop' => 1, 'motivation' => 'I want to build apps for my community.',
    ];
});

function cartRequest(): Application
{
    test()->post(route('site.academy.cart.add', test()->webClass))->assertRedirect();
    test()->post(route('site.academy.cart.add', test()->pyClass))->assertRedirect();
    test()->post(route('site.academy.checkout'), test()->person)->assertRedirect();

    return Application::with('choices')->latest('id')->firstOrFail();
}

test('people put several courses in the cart, one class per course, and register once', function () {
    $this->post(route('site.academy.cart.add', $this->webClass))->assertSessionHas('cart_added');
    $this->post(route('site.academy.cart.add', $this->webLate))->assertSessionHas('cart_added', 'Changed your class for Web Foundations to January class.');
    $this->post(route('site.academy.cart.add', $this->pyClass), ['checkout' => 1])->assertRedirect(route('site.academy.cart'));

    $this->get(route('site.academy.cart'))->assertOk()
        ->assertSee('Web Foundations')->assertSee('January class')->assertDontSee('December class')
        ->assertSee('Python Basics')->assertSee('250,000')->assertSee('Full name');
    $this->get('/courses')->assertSee('s-cart-count', false);

    $this->post(route('site.academy.cart.remove', $this->webLate))->assertRedirect();
    $this->get(route('site.academy.cart'))->assertDontSee('January class')->assertSee('Weekend class');
    $this->post(route('site.academy.cart.add', $this->webClass));

    $this->post(route('site.academy.checkout'), $this->person)->assertRedirect();
    $a = Application::with('choices.course', 'student.user')->firstOrFail();

    expect($a->status)->toBe('submitted')
        ->and($a->choices->pluck('course.title')->all())->toBe(['Python Basics', 'Web Foundations'])
        ->and($a->student->status)->toBe('applicant')
        ->and($a->student->student_no)->toBeNull()                     // no admission number yet
        ->and(session('course_cart'))->toBeNull();

    Mail::assertSent(AcademyMail::class, fn ($m) => $m->hasTo('ada@example.com') && str_contains($m->subjectLine, 'We received your registration'));
    $this->get(route('site.academy.registered', $a->reference))->assertOk()->assertSee('Python Basics')->assertSee('Web Foundations')->assertDontSee('Pay now');
});

test('the cart refuses an empty checkout and a course already waiting for review', function () {
    $this->post(route('site.academy.checkout'), $this->person)->assertSessionHasErrors('cart');
    cartRequest();

    $this->actingAs(User::where('email', 'ada@example.com')->first());
    $this->post(route('site.academy.cart.add', $this->pyClass));
    $this->post(route('site.academy.checkout'), $this->person)->assertSessionHasErrors('cohort_id');
    expect(Application::count())->toBe(1);
});

test('accepting a request gives the admission number, enrolls every course and sends one invoice', function () {
    $a = cartRequest();

    $this->actingAs($this->admin)->get(route('academy.applications.index'))->assertOk()->assertSee('2 courses');
    $this->actingAs($this->admin)->get(route('academy.applications.show', $a))->assertOk()
        ->assertSee('Python Basics')->assertSee('Web Foundations')->assertSee('250,000')->assertSee('Accept and admit');

    $this->actingAs($this->admin)->post(route('academy.applications.admit', $a), ['decision_note' => 'Welcome!'])->assertSessionHas('success');

    $a->refresh()->load('choices', 'student', 'invoice.items');
    $student = $a->student;
    expect($a->status)->toBe('admitted')
        ->and($student->status)->toBe('admitted')
        ->and($student->student_no)->toBe('QCA/' . now()->year . '/0001')
        ->and($a->choices->pluck('status')->unique()->all())->toBe(['admitted'])
        ->and($a->choices->whereNull('enrollment_id'))->toBeEmpty()
        ->and(Enrollment::where('student_id', $student->id)->count())->toBe(2)
        ->and(Invoice::count())->toBe(1)
        ->and((float) $a->invoice->total)->toBe(250000.0)
        ->and($a->invoice->items)->toHaveCount(2)
        ->and($a->invoice->enrollments)->toHaveCount(2);

    Mail::assertSent(AcademyMail::class, fn ($m) => $m->hasTo('ada@example.com')
        && str_contains($m->subjectLine, 'You are admitted: QCA/')
        && $m->buttonText === 'Pay your fees' && str_contains((string) $m->buttonUrl, '/pay/' . $a->invoice_id));

    // Each enrollment points at the shared invoice.
    foreach (Enrollment::all() as $e) {
        expect($e->invoices()->pluck('finance_invoices.id')->all())->toBe([$a->invoice_id]);
    }
});

test('staff can admit to some of the courses only', function () {
    $a = cartRequest();
    $py = $a->choices->firstWhere('course_id', $this->py->id);

    $this->actingAs($this->admin)->post(route('academy.applications.admit', $a), ['choices' => [$py->id]])->assertSessionHas('success');

    $a->refresh()->load('choices', 'invoice');
    expect($a->choices->firstWhere('course_id', $this->py->id)->status)->toBe('admitted')
        ->and($a->choices->firstWhere('course_id', $this->web->id)->status)->toBe('declined')
        ->and(Enrollment::count())->toBe(1)
        ->and((float) $a->invoice->total)->toBe(100000.0)
        ->and($a->invoice->enrollment_id)->toBe(Enrollment::first()->id);   // a single course keeps the old shape

    $this->actingAs($this->admin)->post(route('academy.applications.admit', Application::create([
        'reference' => 'APP-X', 'student_id' => $a->student_id, 'course_id' => $this->web->id, 'cohort_id' => $this->webClass->id, 'status' => 'submitted',
    ])), ['choices' => []])->assertSessionHasErrors('choices');
});

test('declining the request e-mails the reason', function () {
    $a = cartRequest();
    $this->actingAs($this->admin)->post(route('academy.applications.decline', $a), ['decision_note' => 'The classes are full.'])->assertRedirect();

    expect($a->refresh()->status)->toBe('declined')->and($a->student->fresh()->student_no)->toBeNull();
    Mail::assertSent(AcademyMail::class, fn ($m) => $m->hasTo('ada@example.com') && in_array('We are unable to offer you a place this time. The classes are full.', $m->paragraphs, true));
});

test('paying the shared invoice opens every course and e-mails the receipt', function () {
    $a = cartRequest();
    $this->actingAs($this->admin)->post(route('academy.applications.admit', $a));
    $invoice = $a->refresh()->invoice;

    // Staff record a part payment: not enough to open access.
    $this->actingAs($this->admin)->post(route('academy.fees.record', $invoice), ['amount' => 100000, 'method' => 'bank_transfer', 'reference' => 'TRF-1'])
        ->assertSessionHas('success');
    expect(Enrollment::where('access_granted', true)->count())->toBe(0);
    Mail::assertSent(ReceiptMail::class, fn ($m) => $m->hasTo('ada@example.com') && $m->payment->reference === 'TRF-1');

    $this->actingAs($this->admin)->post(route('academy.fees.record', $invoice), ['amount' => 150000, 'method' => 'cash'])->assertSessionHas('success');
    expect($invoice->fresh()->status)->toBe('paid')
        ->and(Enrollment::where('access_granted', true)->count())->toBe(2)
        ->and(Student::first()->status)->toBe('active');
    Mail::assertSent(ReceiptMail::class, 2);

    // The receipt downloads from its signed link, without signing in.
    $payment = Payment::where('reference', 'TRF-1')->first();
    auth()->logout();
    $this->get(ReceiptMailer::downloadUrl($payment))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('site.receipt', $payment))->assertForbidden();
});

test('instalment courses open once every deposit on the shared invoice is paid', function () {
    $this->web->update(['installments_allowed' => true, 'min_deposit' => 50000]);
    $a = cartRequest();
    $this->actingAs($this->admin)->post(route('academy.applications.admit', $a));
    $invoice = $a->refresh()->invoice;

    // Python has no instalments (100,000) + Web's deposit (50,000).
    expect(app(\App\Services\Finance\PaymentService::class)->minimumPayment($invoice))->toBe(15000000);
    $this->actingAs($this->admin)->post(route('academy.fees.record', $invoice), ['amount' => 150000, 'method' => 'pos']);
    expect(Enrollment::where('access_granted', true)->count())->toBe(2)->and($invoice->fresh()->status)->toBe('partially_paid');
});

test('staff send a pay link, by e-mail or to copy for WhatsApp', function () {
    $a = cartRequest();
    $this->actingAs($this->admin)->post(route('academy.applications.admit', $a));
    $invoice = $a->refresh()->invoice;
    Mail::fake();

    $this->actingAs($this->admin)->post(route('academy.fees.send-link', $invoice))->assertSessionHas('pay_link');
    Mail::assertSent(AcademyMail::class, fn ($m) => str_contains($m->subjectLine, 'Pay your fees') && str_contains((string) $m->buttonUrl, '/pay/' . $invoice->id));

    Mail::fake();
    $this->actingAs($this->admin)->post(route('academy.fees.send-link', $invoice), ['email' => 0])->assertSessionHas('pay_link');
    Mail::assertNothingSent();

    $page = $this->actingAs($this->admin)->get(route('academy.students.show', $a->student_id))->assertOk()
        ->assertSee('Fees and payments')->assertSee('Record payment')->assertSee($invoice->number);

    // The link works without signing in and shows both courses.
    auth()->logout();
    $this->get(\App\Services\Academy\ApplicationService::payUrl($invoice))->assertOk()->assertSee('Python Basics')->assertSee('Web Foundations');
});

test('students cannot record payments or send links', function () {
    $a = cartRequest();
    $this->actingAs($this->admin)->post(route('academy.applications.admit', $a));
    $ada = User::where('email', 'ada@example.com')->first();

    $this->actingAs($ada)->post(route('academy.fees.record', $a->refresh()->invoice_id), ['amount' => 1, 'method' => 'cash'])->assertForbidden();
    $this->actingAs($ada)->post(route('academy.fees.send-link', $a->invoice_id))->assertForbidden();
});

test('staff admitting a student by hand gives the admission number; an applicant has none', function () {
    $s = Student::create(['user_id' => User::factory()->create()->id, 'status' => 'applicant']);
    expect($s->student_no)->toBeNull();
    $s->update(['status' => 'admitted']);
    expect($s->fresh()->student_no)->toBe('QCA/' . now()->year . '/0001');

    $t = Student::create(['user_id' => User::factory()->create()->id, 'status' => 'active']);
    expect($t->student_no)->toBe('QCA/' . now()->year . '/0002');
});

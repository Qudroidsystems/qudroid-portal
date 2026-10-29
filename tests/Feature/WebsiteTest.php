<?php

use App\Models\Academy\Application;
use App\Models\Academy\Course;
use App\Models\Crm\Deal;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Models\Website\Form;
use App\Models\Website\FormEntry;
use App\Models\Website\Page;
use App\Models\Website\Product;
use App\Services\Settings\Settings;
use App\Services\Website\Site;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();
});

function websiteManager(): User
{
    $u = User::factory()->create(['user_type' => 'staff']);
    $u->givePermissionTo('Manage website');
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

test('guests get the website at /, with starter content made on the first visit', function () {
    $this->get('/')->assertOk()->assertSee('Qlouds Workspace')->assertSee('Sign in')->assertSee('Invading spheres… improving lives.')
        ->assertSee('Software engineering')->assertSee('AI engineering')->assertSee('Blockchain engineering')
        ->assertSee('We engineer')->assertSee('How we work')->assertSee('data-words', false);
    expect(Page::where('slug', 'home')->exists())->toBeTrue()->and(Product::count())->toBe(4);

    $this->get('/products')->assertOk()->assertSee('Qmail');
    $this->get(route('site.products.show', 'qmail'))->assertOk()->assertSee('Qmail');
    $this->get('/about')->assertOk()->assertSee('We build software people rely on.');
    $this->get('/contact')->assertOk()->assertSee('Send message');
    $this->get('/work')->assertOk();
    $this->get('/courses')->assertOk();
    $this->get('/no-such-page')->assertNotFound();

    // Sample works are drafts: hidden from guests, previewable by website editors.
    $work = \App\Models\Website\Work::first();
    $this->get(route('site.works.show', $work))->assertNotFound();
    $this->actingAs(websiteManager())->get(route('site.works.show', $work))->assertOk()->assertSee('Draft preview');
});

test('signed-in people see a Portal link; with the website off, / goes to sign-in as before', function () {
    $this->actingAs($this->admin)->get('/')->assertOk()->assertSee(route('dashboard'));
    auth()->logout();

    app(Site::class)->save(['enabled' => false]);
    $this->get('/')->assertRedirect(route('login'));
});

test('the contact form stores the entry, adds a CRM lead and tells website managers', function () {
    $manager = websiteManager();
    Form::contact();
    $data = ['f' => ['name' => 'Tola Ade', 'email' => 'tola@example.com', 'phone' => '0803 000 0000', 'company' => 'Tola Foods', 'topic' => 'A new project', 'message' => 'We need an ordering app.']];

    $this->post(route('site.forms.submit', 'contact'), $data)->assertRedirect()->assertSessionHas('form_sent', 'contact');

    $entry = FormEntry::firstOrFail();
    expect($entry->email)->toBe('tola@example.com')->and($entry->data['message'])->toBe('We need an ordering app.')
        ->and($entry->deal_id)->not->toBeNull();
    expect(Deal::find($entry->deal_id)->title)->toContain('Tola');
    expect($manager->notifications()->count())->toBe(1);

    // Bad input and bots.
    $this->post(route('site.forms.submit', 'contact'), ['f' => ['name' => '', 'email' => 'nope', 'topic' => 'Hacking']])
        ->assertSessionHasErrors(['f.name', 'f.email', 'f.topic', 'f.message']);
    $this->post(route('site.forms.submit', 'contact'), $data + ['website' => 'spam']);
    expect(FormEntry::count())->toBe(1);

    // Closed forms take nothing.
    Form::where('slug', 'contact')->update(['status' => 'closed']);
    $this->post(route('site.forms.submit', 'contact'), $data);
    expect(FormEntry::count())->toBe(1);
});

test('managers build pages from blocks; unsafe links are dropped and portal addresses are refused', function () {
    $this->get('/');   // starter content
    $m = websiteManager();
    $blocks = [
        ['type' => 'hero', 'title' => 'Hello Lagos', 'subtitle' => 'Built here.', 'primary_label' => 'Go', 'primary_url' => 'javascript:alert(1)', 'theme' => 'dark', 'evil' => '<script>'],
        ['type' => 'text', 'heading' => 'Our story', 'body' => "**Bold** and <script>alert(1)</script>", 'align' => 'left'],
        ['type' => 'nonsense', 'title' => 'x'],
    ];

    $this->actingAs($m)->post(route('website.pages.store'), ['title' => 'Lagos', 'slug' => 'dashboard', 'status' => 'published', 'blocks' => json_encode($blocks)])
        ->assertSessionHasErrors('slug');
    $this->actingAs($m)->post(route('website.pages.store'), ['title' => 'Lagos office', 'slug' => '', 'status' => 'published', 'in_nav' => 1, 'blocks' => json_encode($blocks)])
        ->assertRedirect();

    $page = Page::where('slug', 'lagos-office')->firstOrFail();
    expect($page->blocks)->toHaveCount(2)->and($page->blocks[0]['primary_url'])->toBe('')->and($page->blocks[0])->not->toHaveKey('evil');

    auth()->logout();
    $this->get('/lagos-office')->assertOk()->assertSee('Hello Lagos')->assertSee('<strong>Bold</strong>', false)
        ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('javascript:alert', false);
    $this->get('/')->assertSee('Lagos office');   // in the top menu

    $this->actingAs($m)->put(route('website.pages.update', $page), ['title' => 'Lagos office', 'slug' => 'lagos-office', 'status' => 'draft', 'blocks' => '[]']);
    auth()->logout();
    $this->get('/lagos-office')->assertNotFound();

    // The home page keeps its address and cannot be deleted.
    $home = Page::where('slug', 'home')->first();
    $this->actingAs($m)->put(route('website.pages.update', $home), ['title' => 'Home', 'slug' => 'other', 'status' => 'draft', 'blocks' => json_encode([['type' => 'hero', 'title' => 'New home']])]);
    expect($home->fresh()->slug)->toBe('home')->and($home->fresh()->status)->toBe('published');
    $this->actingAs($m)->delete(route('website.pages.destroy', $home))->assertStatus(422);
    auth()->logout();
    $this->get('/')->assertSee('New home');
});

test('the steps, counting numbers, moving logo strip and quote slider blocks draw', function () {
    $m = websiteManager();
    $blocks = [
        ['type' => 'steps', 'heading' => 'How we work', 'items' => [['icon' => 'ri-rocket-line', 'title' => 'Launch', 'text' => 'We ship.'], ['icon' => 'javascript:x', 'title' => 'Grow']]],
        ['type' => 'stats', 'items' => [['value' => '1,200+', 'label' => 'students'], ['value' => 'Many', 'label' => 'clients']]],
        ['type' => 'logos', 'scroll' => true, 'items' => [['name' => 'Acme'], ['name' => 'Globex']]],
        ['type' => 'testimonials', 'items' => [['quote' => 'Great team.', 'name' => 'Ada'], ['quote' => 'Fast.', 'name' => 'Bo']]],
    ];
    $this->actingAs($m)->post(route('website.pages.store'), ['title' => 'Motion', 'status' => 'published', 'blocks' => json_encode($blocks)]);
    auth()->logout();
    $this->get('/motion')->assertOk()->assertSee('01')->assertSee('02')->assertSee('Launch')->assertDontSee('javascript:x', false)
        ->assertSee('data-count="1200"', false)->assertSee('data-suffix="+"', false)->assertSee('Many')
        ->assertSee('s-marquee', false)->assertSee('data-slider', false)->assertSee('Great team.');
});

test('managers add products with pictures, and work; staff without the permission cannot', function () {
    Storage::fake('public');
    $m = websiteManager();

    $this->actingAs($m)->post(route('website.products.store'), [
        'name' => 'Qlouds POS', 'tagline' => 'Sell anywhere.', 'status' => 'published', 'icon' => 'ri-store-line', 'color' => '#ff9500',
        'link_url' => 'javascript:x', 'image_file' => UploadedFile::fake()->image('pos.png', 1600, 900),
        'features' => [['icon' => 'ri-wifi-off-line', 'title' => 'Works offline', 'text' => 'Keeps selling when the network drops.'], ['title' => '']],
    ])->assertRedirect();
    $p = Product::where('slug', 'qlouds-pos')->firstOrFail();
    expect($p->features)->toHaveCount(1)->and($p->link_url)->toBeNull()->and($p->image)->toStartWith('website/');
    Storage::disk('public')->assertExists($p->image);
    expect(\App\Models\Website\Media::where('path', $p->image)->exists())->toBeTrue();

    $this->get(route('site.products.show', $p))->assertOk()->assertSee('Works offline')->assertSee('Sell anywhere.');

    $this->actingAs($m)->post(route('website.works.store'), [
        'title' => 'School portal', 'client' => 'Bright Schools', 'year' => 2025, 'status' => 'published', 'tags' => 'Laravel, Flutter, Laravel',
        'results' => [['value' => '3,000', 'label' => 'students'], ['value' => '', 'label' => 'ignored']],
    ])->assertRedirect();
    $w = \App\Models\Website\Work::where('slug', 'school-portal')->firstOrFail();
    expect($w->tags)->toBe(['Laravel', 'Flutter'])->and($w->results)->toHaveCount(1);
    auth()->logout();
    $this->get('/work')->assertSee('School portal');

    $staff = User::factory()->create(['user_type' => 'staff']);
    $staff->forceFill(['must_change_password' => false])->save();
    $this->actingAs($staff)->get(route('website.index'))->assertForbidden();
    $this->actingAs($staff)->post(route('website.products.store'), ['name' => 'X', 'status' => 'published'])->assertForbidden();
});

test('managers build forms, read entries and download them', function () {
    $m = websiteManager();
    $this->actingAs($m)->post(route('website.forms.store'), [
        'name' => 'Request a quote', 'status' => 'open', 'notify' => 'sales@example.com', 'to_crm' => 0,
        'fields' => [
            ['label' => 'Your name', 'type' => 'text', 'required' => 1],
            ['label' => 'Email', 'type' => 'email', 'required' => 1],
            ['label' => 'Budget', 'type' => 'select', 'required' => 1, 'options' => 'Under 1m, 1m to 5m, Over 5m'],
            ['label' => '', 'type' => 'text'],
        ],
    ])->assertRedirect();
    $form = Form::where('slug', 'request-a-quote')->firstOrFail();
    expect($form->fields)->toHaveCount(3)->and($form->fields[2]['options'])->toBe(['Under 1m', '1m to 5m', 'Over 5m']);

    auth()->logout();
    $this->get(route('site.forms.show', $form))->assertOk()->assertSee('Budget');
    $this->post(route('site.forms.submit', $form), ['f' => ['your_name' => 'Ngozi', 'email' => 'n@example.com', 'budget' => 'Over 5m']])->assertSessionHas('form_sent');
    $this->post(route('site.forms.submit', $form), ['f' => ['your_name' => 'Bad', 'email' => 'b@example.com', 'budget' => 'Free']])->assertSessionHasErrors('f.budget');
    expect(FormEntry::where('form_id', $form->id)->count())->toBe(1)->and(FormEntry::first()->deal_id)->toBeNull();

    $this->actingAs($m)->get(route('website.forms.entries', $form))->assertOk()->assertSee('Ngozi')->assertSee('Over 5m');
    $csv = $this->actingAs($m)->get(route('website.forms.export', $form))->assertOk()->streamedContent();
    expect($csv)->toContain('Budget')->toContain('Over 5m');
    $this->actingAs($m)->post(route('website.entries.status', FormEntry::first()), ['status' => 'archived']);
    expect(FormEntry::first()->status)->toBe('archived');
});

test('managers save website settings', function () {
    $this->actingAs(websiteManager())->post(route('website.settings.save'), [
        'enabled' => 1, 'name' => 'Qudroid Systems', 'email' => 'hello@qudroid.com', 'phone' => '+234 800 000 0000',
        'announcement' => 'New cohort starts in December', 'announcement_url' => 'javascript:alert(1)',
        'nav' => ['products' => 1, 'contact' => 1], 'socials' => ['linkedin' => 'https://linkedin.com/company/qudroid'],
    ])->assertSessionHas('success');
    auth()->logout();

    $this->get('/')->assertSee('New cohort starts in December')->assertSee('hello@qudroid.com')->assertSee('linkedin.com/company/qudroid')
        ->assertDontSee('javascript:alert', false);
    expect(app(Site::class)->config()['nav']['work'])->toBeFalse();
});

describe('registering for a course on the website', function () {
    beforeEach(function () {
        Notification::fake();
        $this->course = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 150000, 'currency' => 'NGN', 'status' => 'published', 'created_by' => $this->admin->id]);
        $this->cohort = $this->course->cohorts()->create(['code' => 'WEB101-1', 'name' => 'December class', 'status' => 'open', 'starts_on' => today()->addMonth()]);
        $this->person = [
            'name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone_number' => '08030000000', 'consent' => 1,
            'experience_level' => 'none', 'has_laptop' => 1, 'motivation' => 'I want to build apps for my community.',
        ];
    });

    test('courses taking registrations are listed with their classes', function () {
        $this->get('/courses')->assertOk()->assertSee('Web Foundations');
        $this->get(route('site.academy.course', $this->course->slug))->assertOk()->assertSee('December class')->assertSee('150,000');
        $this->get(route('site.academy.register', [$this->course->slug, $this->cohort]))->assertOk()->assertSee('Full name');
    });

    test('without instant registration the application waits for review', function () {
        $this->post(route('site.academy.store', [$this->course->slug, $this->cohort]), $this->person)->assertRedirect();
        $a = Application::firstOrFail();
        expect($a->status)->toBe('submitted');
        $this->get(route('site.academy.registered', $a->reference))->assertOk()->assertSee('review')->assertDontSee('Pay now');
    });

    test('with instant registration the person is admitted and pays online with OPay', function () {
        $this->course->update(['instant_enrollment' => true]);
        config(['services.paystack.active' => false]);
        app(Settings::class)->put([
            'opay.active' => '1', 'opay.mode' => 'sandbox', 'opay.merchant_id' => '256612345678901',
            'opay.test_public_key' => 'OPAYPUB_test', 'opay.test_secret_key' => 'OPAYPRV_test',
        ]);
        $GLOBALS['siteOpay'] = ['status' => 'INITIAL'];
        Http::fake([
            'testapi.opaycheckout.com/api/v1/international/cashier/create' => fn (HttpRequest $r) => Http::response(['code' => '00000', 'data' => ['cashierUrl' => 'https://sandboxcashier.opaycheckout.com/x', 'reference' => $r['reference']]]),
            'testapi.opaycheckout.com/api/v1/international/cashier/status' => fn (HttpRequest $r) => Http::response(['code' => '00000', 'data' => $GLOBALS['siteOpay'] + ['reference' => $r['reference']]]),
        ]);

        $this->post(route('site.academy.store', [$this->course->slug, $this->cohort]), $this->person)->assertRedirect();
        $a = Application::with('enrollment.invoices')->firstOrFail();
        expect($a->status)->toBe('admitted');
        $invoice = $a->enrollment->invoices->first();

        $page = $this->get(route('site.academy.registered', $a->reference))->assertOk()->assertSee('Pay now')->assertSee('150,000');
        preg_match('#href="([^"]+/pay/' . $invoice->id . '\?[^"]+)"#', $page->getContent(), $m);
        $payUrl = html_entity_decode($m[1]);

        // The pay link must be signed.
        $this->get('/pay/' . $invoice->id)->assertForbidden();
        $this->get($payUrl)->assertOk()->assertSee('Pay with OPay')->assertDontSee('Pay with Paystack');
        $this->post($payUrl, ['amount' => '150000', 'gateway' => 'opay'])->assertRedirect('https://sandboxcashier.opaycheckout.com/x');
        $payment = Payment::where('method', 'opay')->firstOrFail();

        // Coming back before paying shows nothing as paid.
        $this->get(route('site.pay.return', $payment->reference))->assertOk()->assertSee('Almost there');
        expect($payment->fresh()->status)->toBe('pending');

        $GLOBALS['siteOpay'] = ['status' => 'SUCCESS', 'amount' => ['total' => 15000000, 'currency' => 'NGN']];
        $this->get(route('site.pay.return', $payment->reference))->assertOk()->assertSee('Payment received');
        expect($payment->fresh()->status)->toBe('confirmed')->and($invoice->fresh()->status)->toBe('paid');

        // Paid invoices say so.
        $this->get($payUrl)->assertSee('fully paid');
    });

    test('the course page shows payment at registration, and admins switch it on in the course form', function () {
        $this->actingAs($this->admin)->get(route('academy.courses.edit', $this->course))->assertOk()->assertSee('Take payment at registration');
    });
});

test('every website manager page opens', function () {
    $this->get('/');   // starter content
    $m = websiteManager();
    \App\Models\Website\Media::create(['path' => 'website/x.png', 'name' => 'x.png', 'size' => 10]);
    FormEntry::create(['form_id' => Form::contact()->id, 'data' => ['name' => 'A', 'message' => 'Hi'], 'name' => 'A', 'status' => 'new']);

    $urls = [route('website.index'), route('website.settings'), route('website.pages.index'), route('website.products.index'),
        route('website.works.index'), route('website.forms.index'), route('website.media.index'), route('website.media.list'),
        route('website.forms.entries', Form::contact())];
    foreach (Page::all() as $x) $urls[] = route('website.pages.edit', $x);
    foreach (Product::all() as $x) $urls[] = route('website.products.edit', $x);
    foreach (\App\Models\Website\Work::all() as $x) $urls[] = route('website.works.edit', $x);
    foreach (Form::all() as $x) $urls[] = route('website.forms.edit', $x);
    foreach ($urls as $u) $this->actingAs($m)->get($u)->assertOk();
});

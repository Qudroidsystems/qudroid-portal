<?php

namespace App\Services\Website;

use App\Models\Academy\Course;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\User;
use App\Models\Website\Form;
use App\Models\Website\FormEntry;
use App\Models\Website\Page;
use App\Models\Website\Product;
use App\Models\Website\Work;
use App\Services\Messaging\PortalNotifier;
use App\Services\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The public website's settings, menus and form handling. Settings are one
 * JSON value ("website.config") saved from Website → Settings.
 */
class Site
{
    public const SOCIALS = ['x' => 'X (Twitter)', 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube', 'github' => 'GitHub'];

    public function __construct(protected Settings $settings) {}

    public function config(): array
    {
        $saved = $this->settings->json('website.config') ?? [];

        return array_replace_recursive([
            'enabled' => true,
            'name' => config('qudroid.company', 'Qudroid Systems'),
            'tagline' => 'Software, cloud and training for ambitious teams.',
            'description' => config('qudroid.company', 'Qudroid Systems') . ' builds software products, delivers projects for clients and trains the next generation of engineers.',
            'announcement' => '',
            'announcement_url' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'footer_note' => '',
            'socials' => array_fill_keys(array_keys(self::SOCIALS), ''),
            'nav' => ['products' => true, 'work' => true, 'academy' => true, 'contact' => true],
        ], $saved);
    }

    public function save(array $values, ?User $by = null): void
    {
        $this->settings->put(['website.config' => array_replace_recursive($this->config(), $values)], $by);
    }

    /** Is the website the front page? (Off: "/" goes to sign-in as before.) */
    public function enabled(): bool
    {
        try {
            return (bool) $this->config()['enabled'] && Schema::hasTable('site_pages');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** The global navigation: built-in sections, then pages marked "in the menu". */
    public function nav(): array
    {
        $c = $this->config();
        $links = [];
        if ($c['nav']['products'] ?? true) $links[] = ['label' => 'Products', 'url' => route('site.products'), 'active' => 'site.products*'];
        if ($c['nav']['work'] ?? true) $links[] = ['label' => 'Work', 'url' => route('site.works'), 'active' => 'site.works*'];
        if ($c['nav']['academy'] ?? true) $links[] = ['label' => 'Academy', 'url' => route('site.academy'), 'active' => 'site.academy*'];
        foreach (Page::published()->where('in_nav', true)->where('slug', '!=', 'home')->ordered()->get() as $p) {
            $links[] = ['label' => $p->title, 'url' => $p->url(), 'active' => null, 'slug' => $p->slug];
        }
        if ($c['nav']['contact'] ?? true) $links[] = ['label' => 'Contact', 'url' => route('site.contact'), 'active' => 'site.contact*'];

        return $links;
    }

    /** Footer columns, Apple style. */
    public function footer(): array
    {
        $products = Product::published()->ordered()->limit(6)->get()->map(fn ($p) => ['label' => $p->name, 'url' => route('site.products.show', $p)])->all();
        $courses = Course::published()->orderBy('title')->limit(6)->get()->map(fn ($c) => ['label' => $c->title, 'url' => route('site.academy.course', $c->slug)])->all();
        $pages = Page::published()->where('in_footer', true)->where('slug', '!=', 'home')->ordered()->get()->map(fn ($p) => ['label' => $p->title, 'url' => $p->url()])->all();

        return array_filter([
            'Products' => array_merge($products, [['label' => 'All products', 'url' => route('site.products')]]),
            'Academy' => array_merge($courses, [['label' => 'All courses', 'url' => route('site.academy')]]),
            'Company' => array_merge($pages, [['label' => 'Our work', 'url' => route('site.works')], ['label' => 'Contact us', 'url' => route('site.contact')]]),
            'Account' => [['label' => 'Sign in', 'url' => route('login')], ['label' => 'Pay a bill', 'url' => route('billing.index')]],
        ]);
    }

    /** The front page: the saved "home" page, or the starter layout until one is saved. */
    public function homeBlocks(): array
    {
        $home = Page::where('slug', 'home')->first();

        return $home && $home->isPublished() ? (array) $home->blocks : Starter::homeBlocks($this->config());
    }

    // ── Forms ───────────────────────────────────────────────────────────

    /** Validation rules for a form's fields. */
    public function rules(Form $form): array
    {
        $rules = [];
        foreach ((array) $form->fields as $f) {
            $key = 'f.' . $f['key'];
            $base = !empty($f['required']) ? ['required'] : ['nullable'];
            $rules[$key] = array_merge($base, match ($f['type']) {
                'email' => ['email:rfc', 'max:190'],
                'phone' => ['string', 'max:40', 'regex:/^[0-9 +()\-]{6,40}$/'],
                'number' => ['numeric'],
                'date' => ['date'],
                'textarea' => ['string', 'max:5000'],
                'select' => [Rule::in($f['options'] ?? [])],
                'checkbox' => !empty($f['required']) ? ['accepted'] : ['boolean'],
                default => ['string', 'max:300'],
            });
        }

        return $rules;
    }

    /** Store an entry, tell the team, and (if switched on) open a CRM deal. */
    public function submit(Form $form, array $values, ?string $ip = null, ?string $page = null): FormEntry
    {
        $data = [];
        $byType = [];
        foreach ((array) $form->fields as $f) {
            $v = $values[$f['key']] ?? null;
            if ($f['type'] === 'checkbox') $v = (bool) $v;
            $data[$f['key']] = is_string($v) ? trim($v) : $v;
            $byType[$f['type']] ??= $data[$f['key']];
        }
        $name = $data['name'] ?? $data['full_name'] ?? ($byType['text'] ?? null);
        $email = $byType['email'] ?? null;
        $phone = $byType['phone'] ?? null;

        $entry = DB::transaction(function () use ($form, $data, $name, $email, $phone, $ip, $page) {
            $entry = $form->entries()->create([
                'data' => $data, 'name' => $name ? mb_substr($name, 0, 150) : null, 'email' => $email, 'phone' => $phone,
                'ip_address' => $ip, 'page' => $page ? mb_substr($page, 0, 300) : null,
            ]);

            if ($form->to_crm && ($email || $phone)) {
                $contact = ($email ? Contact::where('email', $email)->first() : null)
                    ?? Contact::create(['name' => $name ?: ($email ?: $phone), 'email' => $email, 'phone' => $phone, 'notes' => 'From the website (' . $form->name . ').']);
                $deal = Deal::create([
                    'title' => Str::limit($form->name . ': ' . ($name ?: $email), 140, ''),
                    'contact_id' => $contact->id,
                    'company_id' => $contact->company_id,
                    'value' => 0,
                    'currency' => 'NGN',
                    'stage' => 'new',
                    'source' => 'Website',
                    'notes' => $this->summary($form, $data),
                    'position' => (int) Deal::where('stage', 'new')->max('position') + 1,
                ]);
                $entry->update(['deal_id' => $deal->id]);
            }

            return $entry;
        });

        $title = 'Website: ' . $form->name;
        $body = ($name ?: $email ?: 'Someone') . ' sent the ' . $form->name . ' form.';
        PortalNotifier::toUsers(PortalNotifier::userIdsWithPermission('Manage website'), $title, $body, route('website.forms.entries', $form), 'system');

        foreach (array_filter(array_map('trim', explode(',', (string) $form->notify))) as $to) {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) continue;
            try {
                Mail::raw($body . "\n\n" . $this->summary($form, $data) . "\n\n" . route('website.forms.entries', $form), function ($m) use ($to, $title, $email) {
                    $m->to($to)->subject($title);
                    if ($email) $m->replyTo($email);
                });
            } catch (\Throwable $e) {
                Log::warning('Website form e-mail failed', ['form' => $form->slug, 'error' => $e->getMessage()]);
            }
        }

        return $entry;
    }

    public function summary(Form $form, array $data): string
    {
        $lines = [];
        foreach ((array) $form->fields as $f) {
            $v = $data[$f['key']] ?? null;
            if ($v === null || $v === '') continue;
            $lines[] = $f['label'] . ': ' . (is_bool($v) ? ($v ? 'Yes' : 'No') : $v);
        }

        return implode("\n", $lines);
    }

    /** Clean form fields typed in the form builder. */
    public static function cleanFields(array $fields): array
    {
        $out = [];
        $keys = [];
        foreach (array_slice($fields, 0, 30) as $f) {
            $label = trim((string) ($f['label'] ?? ''));
            if ($label === '') continue;
            $type = array_key_exists($f['type'] ?? '', Form::TYPES) ? $f['type'] : 'text';
            $key = Str::snake(Str::limit(preg_replace('/[^A-Za-z0-9 ]/', '', $f['key'] ?? '') ?: $label, 40, ''));
            $key = $key ?: 'field';
            $base = $key;
            for ($i = 2; in_array($key, $keys, true); $i++) $key = $base . '_' . $i;
            $keys[] = $key;
            $options = $type === 'select'
                ? array_values(array_filter(array_map(fn ($o) => mb_substr(trim($o), 0, 100), is_array($f['options'] ?? null) ? $f['options'] : preg_split('/\r?\n|,/', (string) ($f['options'] ?? '')))))
                : [];
            $out[] = array_filter([
                'key' => $key, 'label' => mb_substr($label, 0, 150), 'type' => $type, 'required' => (bool) ($f['required'] ?? false),
                'options' => $options ?: null, 'help' => mb_substr(trim((string) ($f['help'] ?? '')), 0, 200) ?: null,
            ], fn ($v) => $v !== null);
        }

        return $out;
    }
}

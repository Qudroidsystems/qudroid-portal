<?php

namespace App\Services\Website;

use App\Models\User;
use App\Models\Website\Form;
use App\Models\Website\Page;
use App\Models\Website\Product;
use App\Models\Website\Work;

/**
 * Starter content, so the website looks finished on day one. The front page
 * uses homeBlocks() until a "home" page is saved; install() copies everything
 * into the database so it can be edited. Sample case studies are drafts: they
 * only go live once someone replaces them with real work and publishes them.
 */
class Starter
{
    public const SERVICES_INTRO = 'Software, AI and blockchain engineering, cloud and training, from one team.';

    public const HERO_TITLE = 'We engineer';
    public const HERO_WORDS = 'software, AI, blockchain, mobile apps, the cloud';

    /** How we work: the home page's steps block. */
    public static function stepsBlock(): array
    {
        return ['type' => 'steps', 'eyebrow' => 'How we work', 'heading' => 'Four steps from idea to impact.',
            'intro' => 'A clear process, with you involved at every stage.', 'theme' => 'light', 'items' => [
                ['icon' => 'ri-search-eye-line', 'title' => 'Discover', 'text' => 'We learn your goals, users and constraints, and agree what success looks like.'],
                ['icon' => 'ri-pencil-ruler-2-line', 'title' => 'Design', 'text' => 'Architecture, user experience and a plan you can see before we build.'],
                ['icon' => 'ri-code-box-line', 'title' => 'Build', 'text' => 'Short cycles with working software to try every couple of weeks.'],
                ['icon' => 'ri-rocket-line', 'title' => 'Launch and grow', 'text' => 'We ship, monitor and keep improving it with you.'],
            ]];
    }

    /** What we do: the home page's features block. */
    public static function services(): array
    {
        return [
            ['icon' => 'ri-code-s-slash-line', 'title' => 'Software engineering', 'text' => 'Web platforms, portals and internal tools, designed and built around how you work.'],
            ['icon' => 'ri-robot-line', 'title' => 'AI engineering', 'text' => 'Machine learning, AI assistants and data products that put your information to work.'],
            ['icon' => 'ri-links-line', 'title' => 'Blockchain engineering', 'text' => 'Smart contracts, digital assets and secure, verifiable records on the blockchain.'],
            ['icon' => 'ri-smartphone-line', 'title' => 'Mobile apps', 'text' => 'iPhone and Android apps that feel at home on each.'],
            ['icon' => 'ri-cloud-line', 'title' => 'Cloud and DevOps', 'text' => 'Hosting, automation, monitoring and security that scale with you.'],
            ['icon' => 'ri-book-open-line', 'title' => 'Training', 'text' => 'Hands-on courses at Qudroid Academy for teams and individuals.'],
        ];
    }

    public static function homeBlocks(array $c): array
    {
        return [
            ['type' => 'hero', 'eyebrow' => $c['name'], 'title' => self::HERO_TITLE, 'rotating' => self::HERO_WORDS,
                'subtitle' => $c['tagline'], 'primary_label' => 'Explore products', 'primary_url' => '/products',
                'secondary_label' => 'Start a project', 'secondary_url' => '/contact', 'image' => '', 'theme' => 'dark'],
            ['type' => 'tiles', 'source' => 'products', 'items' => []],
            ['type' => 'features', 'eyebrow' => 'What we do', 'heading' => 'From idea to launch, and beyond.',
                'intro' => self::SERVICES_INTRO, 'theme' => 'grey', 'items' => self::services()],
            self::stepsBlock(),
            ['type' => 'split', 'eyebrow' => 'Qlouds Workspace', 'heading' => 'Every department. One workspace.',
                'body' => "Chat and calls, meetings, Drive, mail, projects, CRM, finance, HR and payroll, all in one place, on the web and on your phone.",
                'image' => '', 'image_side' => 'right', 'link_label' => 'Learn more', 'url' => '/products/qlouds-workspace', 'theme' => 'light'],
            ['type' => 'works', 'heading' => 'Work we are proud of.', 'intro' => 'A few of the projects we have delivered for clients.', 'featured_only' => true],
            ['type' => 'courses', 'heading' => 'Learn with Qudroid Academy.', 'intro' => 'Practical, project-based courses taught by working engineers. Register and pay online.'],
            ['type' => 'cta', 'title' => 'Have a project in mind?', 'text' => 'Tell us what you are building. We reply within one working day.',
                'button_label' => 'Talk to us', 'url' => '/contact', 'theme' => 'blue'],
        ];
    }

    public static function products(): array
    {
        return [
            ['slug' => 'qlouds-workspace', 'name' => 'Qlouds Workspace', 'tagline' => 'Every department. One workspace.', 'category' => 'Platform',
                'icon' => 'ri-apps-2-line', 'color' => '#0EA5E9', 'featured' => true,
                'summary' => 'Chat, calls, meetings, Drive, mail, projects, CRM, finance, HR and payroll in one secure workspace.',
                'body' => "Qlouds Workspace brings your whole company into one place.\n\n- **Talk**: channels, direct messages, voice and video calls, huddles.\n- **Work**: projects and tasks on live boards, docs and Drive.\n- **Run the business**: CRM, invoicing and payments, inventory, HR, leave and payroll.\n- **See it all**: the Command Center shows money, people and projects at a glance.",
                'features' => [
                    ['icon' => 'ri-chat-smile-3-line', 'title' => 'Chat and calls', 'text' => 'Channels, DMs, huddles, voice and video.'],
                    ['icon' => 'ri-layout-column-line', 'title' => 'Projects', 'text' => 'Boards, tasks, time and approvals.'],
                    ['icon' => 'ri-money-dollar-circle-line', 'title' => 'Finance', 'text' => 'Invoices, Paystack and OPay, ledger.'],
                    ['icon' => 'ri-team-line', 'title' => 'HR and payroll', 'text' => 'Leave, payslips and PAYE.'],
                ], 'link_label' => 'Request a demo', 'link_url' => '/contact'],
            ['slug' => 'qmail', 'name' => 'Qmail', 'tagline' => 'Your own e-mail, beautifully simple.', 'category' => 'Productivity',
                'icon' => 'ri-mail-line', 'color' => '#EA4335', 'featured' => true,
                'summary' => 'Business e-mail on your own domain with conversations, snooze, scheduled send and search.',
                'body' => "Qmail gives every person an address on your domain, with a fast inbox on the web and on mobile.",
                'features' => [
                    ['icon' => 'ri-chat-3-line', 'title' => 'Conversations', 'text' => 'Replies grouped together.'],
                    ['icon' => 'ri-time-line', 'title' => 'Snooze and send later', 'text' => 'Deal with mail when it suits you.'],
                    ['icon' => 'ri-search-line', 'title' => 'Search', 'text' => 'Find anything with simple operators.'],
                ], 'link_label' => 'Get Qmail', 'link_url' => '/contact'],
            ['slug' => 'qudroid-academy', 'name' => 'Qudroid Academy LMS', 'tagline' => 'Run a school online.', 'category' => 'Education',
                'icon' => 'ri-book-open-line', 'color' => '#6366F1', 'featured' => true,
                'summary' => 'Admissions, courses, lessons, assessments, attendance, fees and certificates with QR verification.',
                'body' => "Everything a training school needs, from the first application to the final certificate.",
                'features' => [
                    ['icon' => 'ri-user-add-line', 'title' => 'Admissions', 'text' => 'Online applications and payment.'],
                    ['icon' => 'ri-book-open-line', 'title' => 'Learning', 'text' => 'Lessons, quizzes and assignments.'],
                    ['icon' => 'ri-award-line', 'title' => 'Certificates', 'text' => 'Numbered, with QR verification.'],
                ], 'link_label' => 'See our courses', 'link_url' => '/courses'],
            ['slug' => 'qlouds-mobile', 'name' => 'Qlouds for iPhone and Android', 'tagline' => 'The whole workspace, in your pocket.', 'category' => 'Mobile',
                'icon' => 'ri-smartphone-line', 'color' => '#0F172A', 'featured' => true,
                'summary' => 'Chat, calls, mail, approvals, bills, tasks and courses on your phone, with notifications that let you act straight away.',
                'body' => "Approve leave from a notification, pay a bill, join a call, check in with a QR code.",
                'features' => [], 'link_label' => 'Talk to us', 'link_url' => '/contact'],
            ...self::industryProducts(),
        ];
    }

    /** Products built for particular industries: property, schools, universities, fashion, logistics. */
    public static function industryProducts(): array
    {
        return [
            ['slug' => 'homilet', 'name' => 'Homilet', 'tagline' => 'Find your next home. See it before you go.', 'category' => 'Property',
                'icon' => 'ri-home-smile-line', 'color' => '#10B981', 'featured' => true,
                'summary' => 'A house and home renting app for tenants, landlords and agents: browse homes with photos, facilities and location, then book a viewing.',
                'body' => "Homilet connects people looking for a home with landlords and agencies.\n\n- **Browse** houses and apartments with photos, prices and the facilities each one has.\n- **See the location** on a map, with what is nearby.\n- **Shortlist and enquire** without endless phone calls.\n- **For agents and landlords**: list properties, manage enquiries and keep track of viewings.",
                'features' => [
                    ['icon' => 'ri-image-2-line', 'title' => 'Photos and facilities', 'text' => 'See each home and what comes with it.'],
                    ['icon' => 'ri-map-pin-line', 'title' => 'Location', 'text' => 'Where it is, and what is around it.'],
                    ['icon' => 'ri-search-line', 'title' => 'Search and filters', 'text' => 'By area, price, rooms and facilities.'],
                    ['icon' => 'ri-key-2-line', 'title' => 'For agents', 'text' => 'List homes and manage enquiries.'],
                ], 'link_label' => 'Talk to us', 'link_url' => '/contact'],
            ['slug' => 'viteschools', 'name' => 'ViteSchools', 'tagline' => 'Secondary school management, made simple.', 'category' => 'Education',
                'icon' => 'ri-book-2-line', 'color' => '#F59E0B', 'featured' => true,
                'summary' => 'School management software for secondary schools: students, classes, results, fees and communication with parents.',
                'body' => "ViteSchools runs the day-to-day of a secondary school in one place, for administrators, teachers, students and parents.\n\n- **Students and classes**: admissions, class lists and records.\n- **Results**: scores, report cards and term results.\n- **Fees**: bills, payments and balances.\n- **Parents**: keep them informed.",
                'features' => [
                    ['icon' => 'ri-team-line', 'title' => 'Students and classes', 'text' => 'Admissions, records and class lists.'],
                    ['icon' => 'ri-file-list-3-line', 'title' => 'Results', 'text' => 'Scores and report cards.'],
                    ['icon' => 'ri-wallet-3-line', 'title' => 'School fees', 'text' => 'Bills, payments and balances.'],
                    ['icon' => 'ri-parent-line', 'title' => 'Parents', 'text' => 'Keep families in the loop.'],
                ], 'link_label' => 'Request a demo', 'link_url' => '/contact'],
            ['slug' => 'univite', 'name' => 'Univite', 'tagline' => 'The university portal, end to end.', 'category' => 'Education',
                'icon' => 'ri-government-line', 'color' => '#7C3AED', 'featured' => false,
                'summary' => 'A university portal and school system: admissions, registration, courses, results and fees for students, lecturers and staff.',
                'body' => "Univite is a complete portal for universities and other higher institutions.\n\n- **Admissions** and student records.\n- **Course registration** each semester.\n- **Results** and transcripts.\n- **Fees and payments** online.",
                'features' => [
                    ['icon' => 'ri-user-star-line', 'title' => 'Admissions', 'text' => 'From application to matriculation.'],
                    ['icon' => 'ri-calendar-check-line', 'title' => 'Course registration', 'text' => 'Semester by semester.'],
                    ['icon' => 'ri-bar-chart-box-line', 'title' => 'Results', 'text' => 'Grades and transcripts.'],
                    ['icon' => 'ri-bank-card-line', 'title' => 'Fees', 'text' => 'Online payment and receipts.'],
                ], 'link_label' => 'Request a demo', 'link_url' => '/contact'],
            ['slug' => 'fashion-house', 'name' => 'Fashion House', 'tagline' => 'Run your fashion business from one app.', 'category' => 'Retail',
                'icon' => 'ri-t-shirt-line', 'color' => '#EC4899', 'featured' => false,
                'summary' => 'Software for fashion houses and tailors: customers and their measurements, orders, production and sales.',
                'body' => "Fashion House helps designers and tailoring businesses keep every customer, order and outfit on track.\n\n- **Customers and measurements**, saved for next time.\n- **Orders** from first fitting to delivery.\n- **Sales and payments** in one place.",
                'features' => [
                    ['icon' => 'ri-ruler-line', 'title' => 'Measurements', 'text' => 'Saved for every customer.'],
                    ['icon' => 'ri-scissors-line', 'title' => 'Orders', 'text' => 'Track each outfit to delivery.'],
                    ['icon' => 'ri-shopping-bag-line', 'title' => 'Sales', 'text' => 'Payments and balances.'],
                ], 'link_label' => 'Talk to us', 'link_url' => '/contact'],
            ['slug' => 'logistics', 'name' => 'Logistics', 'tagline' => 'Every delivery, tracked.', 'category' => 'Logistics',
                'icon' => 'ri-truck-line', 'color' => '#F97316', 'featured' => false,
                'summary' => 'Logistics software for delivery and dispatch businesses: orders, riders and drivers, routes and tracking.',
                'body' => "Manage deliveries from pickup to drop-off.\n\n- **Orders and dispatch**: assign jobs to riders and drivers.\n- **Tracking**: know where every delivery is.\n- **Customers** get updates along the way.",
                'features' => [
                    ['icon' => 'ri-route-line', 'title' => 'Dispatch', 'text' => 'Assign jobs to riders and drivers.'],
                    ['icon' => 'ri-map-pin-line', 'title' => 'Tracking', 'text' => 'Where every delivery is.'],
                    ['icon' => 'ri-store-2-line', 'title' => 'For businesses', 'text' => 'Book and follow deliveries.'],
                ], 'link_label' => 'Talk to us', 'link_url' => '/contact'],
        ];
    }

    public static function works(): array
    {
        return [
            ['slug' => 'sample-school-portal', 'title' => 'Sample: a school portal', 'client' => 'Replace with a real client', 'category' => 'Web platform', 'year' => (int) now()->year,
                'summary' => 'This is a sample case study. Replace it with a real project, then publish it.', 'color' => '#6366F1', 'featured' => true,
                'body' => "Describe the problem, what you built and the result.", 'tags' => ['Laravel', 'Flutter'], 'results' => [['value' => '—', 'label' => 'Add a result']]],
            ['slug' => 'sample-fintech-app', 'title' => 'Sample: a payments app', 'client' => 'Replace with a real client', 'category' => 'Mobile app', 'year' => (int) now()->year,
                'summary' => 'This is a sample case study. Replace it with a real project, then publish it.', 'color' => '#10B981', 'featured' => true,
                'body' => "Describe the problem, what you built and the result.", 'tags' => ['iOS', 'Android'], 'results' => []],
        ];
    }

    /** Copy the starter content into the database (only what isn't there yet). */
    public static function install(array $config, ?User $by = null): array
    {
        $made = ['pages' => 0, 'products' => 0, 'works' => 0];
        if (!Page::where('slug', 'home')->exists()) {
            Page::create(['slug' => 'home', 'title' => 'Home', 'description' => $config['description'] ?? null,
                'blocks' => self::homeBlocks($config), 'status' => 'published', 'updated_by' => $by?->id]);
            $made['pages']++;
        }
        if (!Page::where('slug', 'about')->exists()) {
            Page::create(['slug' => 'about', 'title' => 'About', 'status' => 'published', 'in_nav' => false, 'in_footer' => true, 'updated_by' => $by?->id,
                'description' => 'Who we are and how we work.', 'blocks' => [
                    ['type' => 'hero', 'eyebrow' => 'About us', 'title' => 'We build software people rely on.', 'subtitle' => $config['description'] ?? '',
                        'primary_label' => '', 'primary_url' => '', 'secondary_label' => '', 'secondary_url' => '', 'image' => '', 'theme' => 'light'],
                    ['type' => 'text', 'heading' => 'Our story', 'body' => 'Tell your story here: when you started, what you believe and who you serve. Edit this page in the portal under Website → Pages.', 'align' => 'center'],
                    ['type' => 'cta', 'title' => 'Work with us', 'text' => '', 'button_label' => 'Contact us', 'url' => '/contact', 'theme' => 'grey'],
                ]]);
            $made['pages']++;
        }
        foreach (self::products() as $i => $p) {
            if (Product::where('slug', $p['slug'])->exists()) continue;
            Product::create($p + ['status' => 'published', 'position' => $i]);
            $made['products']++;
        }
        foreach (self::works() as $i => $w) {
            if (Work::where('slug', $w['slug'])->exists()) continue;
            Work::create($w + ['status' => 'draft', 'position' => $i]);
            $made['works']++;
        }
        Form::contact();

        return $made;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The school's public website, managed from the portal ("Website" menu).
 *
 * Content the school writes lives here; everything the portal already knows
 * (school name, logo, address, phones, classes, subjects, public calendar
 * events, staff, enrolment numbers) is read live from the portal's own tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('website_settings')) {
            Schema::create('website_settings', function (Blueprint $t) {
                $t->string('key', 80)->primary();
                $t->longText('value')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('website_slides')) {
            Schema::create('website_slides', function (Blueprint $t) {
                $t->id();
                $t->string('title', 150)->nullable();
                $t->string('subtitle', 255)->nullable();
                $t->string('image')->nullable();
                $t->string('button_text', 60)->nullable();
                $t->string('button_link')->nullable();
                $t->unsignedSmallInteger('sort')->default(0);
                $t->boolean('published')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('website_pages')) {
            Schema::create('website_pages', function (Blueprint $t) {
                $t->id();
                $t->string('title', 150);
                $t->string('slug', 160)->unique();
                $t->string('menu', 30)->nullable();          // about | admissions | academics | life | none
                $t->string('summary', 300)->nullable();
                $t->longText('body')->nullable();
                $t->string('banner')->nullable();
                $t->unsignedSmallInteger('sort')->default(0);
                $t->boolean('published')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('website_posts')) {
            Schema::create('website_posts', function (Blueprint $t) {
                $t->id();
                $t->string('type', 20)->default('news');       // news | event | story
                $t->string('title', 200);
                $t->string('slug', 220)->unique();
                $t->string('category', 60)->nullable();
                $t->string('excerpt', 400)->nullable();
                $t->longText('body')->nullable();
                $t->string('cover')->nullable();
                $t->date('event_date')->nullable();
                $t->string('event_time', 40)->nullable();
                $t->string('location', 150)->nullable();
                $t->boolean('featured')->default(false);
                $t->boolean('published')->default(true);
                $t->timestamp('published_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['type', 'published', 'published_at']);
            });
        }

        if (!Schema::hasTable('website_albums')) {
            Schema::create('website_albums', function (Blueprint $t) {
                $t->id();
                $t->string('title', 150);
                $t->string('slug', 160)->unique();
                $t->string('description', 500)->nullable();
                $t->date('taken_on')->nullable();
                $t->unsignedBigInteger('cover_photo_id')->nullable();
                $t->unsignedSmallInteger('sort')->default(0);
                $t->boolean('published')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('website_photos')) {
            Schema::create('website_photos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('album_id')->index();
                $t->string('path');
                $t->string('thumb')->nullable();
                $t->string('caption', 255)->nullable();
                $t->unsignedInteger('width')->nullable();
                $t->unsignedInteger('height')->nullable();
                $t->unsignedSmallInteger('sort')->default(0);
                $t->timestamps();
            });
        }

        // Simple lists: testimonials, FAQs, core values, partners, highlights.
        if (!Schema::hasTable('website_items')) {
            Schema::create('website_items', function (Blueprint $t) {
                $t->id();
                $t->string('kind', 20)->index();               // testimonial | faq | value | partner | highlight
                $t->string('title', 200)->nullable();
                $t->text('body')->nullable();
                $t->string('subtitle', 200)->nullable();       // e.g. "Parent, JSS 2"
                $t->string('image')->nullable();
                $t->string('link')->nullable();
                $t->string('icon', 60)->nullable();
                $t->unsignedSmallInteger('sort')->default(0);
                $t->boolean('published')->default(true);
                $t->timestamps();
            });
        }

        // Staff shown on the "Our people" page (chosen by the admin).
        if (!Schema::hasTable('website_people')) {
            Schema::create('website_people', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable()->index();
                $t->string('name', 150);
                $t->string('role', 150)->nullable();
                $t->string('group', 40)->default('staff');     // leadership | staff | board
                $t->text('bio')->nullable();
                $t->string('photo')->nullable();
                $t->unsignedSmallInteger('sort')->default(0);
                $t->boolean('published')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('website_messages')) {
            Schema::create('website_messages', function (Blueprint $t) {
                $t->id();
                $t->string('type', 20)->default('contact');    // contact | admission | visit | newsletter
                $t->string('name', 150)->nullable();
                $t->string('email', 150)->nullable();
                $t->string('phone', 40)->nullable();
                $t->string('subject', 200)->nullable();
                $t->text('message')->nullable();
                $t->json('data')->nullable();                  // admission: child name, class, date of birth…
                $t->string('ip', 45)->nullable();
                $t->timestamp('read_at')->nullable();
                $t->timestamps();
            });
        }

        // Starter content, so the site looks complete on day one.
        $now = now();
        if (!DB::table('website_settings')->exists()) {
            $school = Schema::hasTable('school_information') ? DB::table('school_information')->first() : null;
            $name = $school->school_name ?? config('app.name');
            foreach ([
                'enabled' => '1',
                'primary_color' => '#AE152D',
                'secondary_color' => '#0B3D2E',
                'accent_color' => '#2E9E5B',
                'hero_title' => 'Welcome to ' . $name,
                'hero_subtitle' => $school->school_motto ?? 'Raising confident, caring and capable young people.',
                'hero_button_text' => 'Admission ongoing — apply now',
                'hero_button_link' => '/admissions/apply',
                'intro_title' => 'A school where every child is known',
                'intro_body' => '<p>' . e($name) . ' combines strong academics with character, creativity and care. Our teachers know every learner by name, track progress closely and partner with parents through the school portal and mobile app.</p>',
                'admission_open' => '1',
                'admission_text' => '<p>Admission into all classes is in progress. Entrance examinations hold every Saturday. Visit the school or apply online today.</p>',
                'footer_about' => 'Raising leaders with knowledge, skill and character.',
                'social_facebook' => '', 'social_instagram' => '', 'social_x' => '', 'social_youtube' => '', 'social_tiktok' => '',
                'whatsapp' => '',
                'map_embed' => '',
                'show_stats' => '1',
                'meta_description' => $name . ' — admissions, academics, news, events and gallery.',
                'app_name' => $name . ' App',
                'app_play_url' => '', 'app_ios_url' => '', 'app_apk' => '', 'app_version' => '1.0.0',
                'app_intro' => 'Results, fees, attendance, timetable, homework and school news — in your pocket.',
            ] as $k => $v) {
                DB::table('website_settings')->insert(['key' => $k, 'value' => $v, 'created_at' => $now, 'updated_at' => $now]);
            }

            foreach ([['value', 'Excellence', 'We aim high in learning and in life.', 'ri-medal-line'],
                      ['value', 'Integrity', 'We do the right thing, even when no one is watching.', 'ri-shield-check-line'],
                      ['value', 'Discipline', 'Self-control and respect guide how we learn and live.', 'ri-compass-3-line'],
                      ['value', 'Creativity', 'We imagine, build and solve problems together.', 'ri-lightbulb-flash-line'],
                      ['value', 'Service', 'We care for one another and our community.', 'ri-hand-heart-line'],
                      ['value', 'Faith', 'Strong values shape strong character.', 'ri-sun-line']] as $i => [$kind, $title, $body, $icon]) {
                DB::table('website_items')->insert(['kind' => $kind, 'title' => $title, 'body' => $body, 'icon' => $icon, 'sort' => $i, 'published' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
            foreach ([['How do I apply for admission?', 'Fill the online application form on the Admissions page or visit the school office. You will be invited for an entrance assessment and interview.'],
                      ['Can school fees be paid in instalments?', 'Yes. Instalment plans are available, and fees can be paid online through the parent portal or the mobile app.'],
                      ['How do parents follow their child\'s progress?', 'Parents get a portal account and the school\'s mobile app to see results, attendance, fees, homework and notices.'],
                      ['Do you offer extra-curricular activities?', 'Yes — clubs, sports, music, debate, coding and more run every week.']] as $i => [$q, $a]) {
                DB::table('website_items')->insert(['kind' => 'faq', 'title' => $q, 'body' => $a, 'sort' => $i, 'published' => true, 'created_at' => $now, 'updated_at' => $now]);
            }

            foreach ([
                ['About us', 'about-us', 'about', 'Who we are, our story and our mission.', '<h2>Our story</h2><p>Tell visitors how the school began, what makes it different and where it is going.</p><h2>Our mission</h2><p>To provide a safe, stimulating environment where every learner grows in knowledge, skill and character.</p><h2>Our vision</h2><p>To raise confident leaders who serve their communities with excellence.</p>'],
                ['Principal\'s welcome', 'principals-welcome', 'about', 'A message from the head of school.', '<p>Dear parents and visitors, welcome to our school. ...</p>'],
                ['Admission criteria', 'admission-criteria', 'admissions', 'Who can apply and what is required.', '<ul><li>Completed application form</li><li>Last two report cards</li><li>Birth certificate</li><li>Two passport photographs</li><li>Entrance assessment and interview</li></ul>'],
                ['Admission policy', 'admission-policy', 'admissions', 'How admissions are decided.', '<p>Describe how places are offered, deadlines, and scholarship rules.</p>'],
                ['Our curriculum', 'curriculum', 'academics', 'What and how our students learn.', '<p>Describe the curriculum (Nigerian / British / blended), exams students sit (BECE, WAEC, NECO, IGCSE), and the teaching approach.</p>'],
                ['School uniform', 'school-uniform', 'life', 'Uniform for every day of the week.', '<p>Describe the uniform and sports wear.</p>'],
                ['Clubs & activities', 'clubs-and-activities', 'life', 'Learning beyond the classroom.', '<p>List clubs, sports, excursions and competitions.</p>'],
            ] as $i => [$title, $slug, $menu, $summary, $body]) {
                DB::table('website_pages')->insert(['title' => $title, 'slug' => $slug, 'menu' => $menu, 'summary' => $summary, 'body' => $body, 'sort' => $i, 'published' => true, 'created_at' => $now, 'updated_at' => $now]);
            }

            DB::table('website_posts')->insert([
                'type' => 'news', 'title' => 'Our new website and mobile app are live', 'slug' => 'new-website-and-mobile-app',
                'category' => 'News', 'excerpt' => 'Parents can now follow results, fees, attendance and homework from their phones.',
                'body' => '<p>We are pleased to launch our new website and mobile app. Download the app from the <a href="/app">app page</a>, sign in with your portal details and stay connected.</p>',
                'featured' => true, 'published' => true, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Permission for the Website menu, given to the admin roles that exist.
        if (Schema::hasTable('permissions')) {
            $id = DB::table('permissions')->where('name', 'Manage website')->where('guard_name', 'web')->value('id');
            if (!$id) {
                $row = ['name' => 'Manage website', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now];
                if (Schema::hasColumn('permissions', 'title')) $row['title'] = 'Website';
                $id = DB::table('permissions')->insertGetId($row);
            }
            foreach (DB::table('roles')->whereIn('name', ['Super Admin', 'Admin', 'Administrator', 'super-admin', 'admin'])->pluck('id') as $rid) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $rid]);
            }
            try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        foreach (['website_messages', 'website_people', 'website_items', 'website_photos', 'website_albums', 'website_posts', 'website_pages', 'website_slides', 'website_settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

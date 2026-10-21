<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes everything the /website-preview home page shows an editable default in
 * the database, so the admin starts from the full site and edits it under
 * Website › Settings / Home slider / Highlights.
 *
 * Safe to run on a portal that already has content: it only INSERTS what is
 * missing (settings keys that don't exist, a slide/highlights only when none
 * exist) and never overwrites anything the admin has written.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('website_settings')) return;
        $now = now();
        $school = Schema::hasTable('school_information') ? DB::table('school_information')->orderByDesc('is_active')->first() : null;
        $name = $school->school_name ?? config('app.name');
        $motto = $school->school_motto ?? 'Raising confident, caring and capable young people.';

        // ── settings (texts that used to be hardcoded in the home page) ──
        $defaults = [
            'enabled' => '1', 'primary_color' => '#AE152D', 'secondary_color' => '#0B3D2E', 'accent_color' => '#2E9E5B',
            'hero_title' => 'Welcome to ' . $name, 'hero_subtitle' => $motto,
            'hero_button_text' => 'Admission ongoing — apply now', 'hero_button_link' => '/admissions#apply',
            'intro_title' => 'A school where every child is known',
            'intro_body' => '<p>' . e($name) . ' combines strong academics with character, creativity and care. Our teachers know every learner by name, track progress closely and partner with parents through the school portal and mobile app.</p>',
            'admission_open' => '1',
            'admission_text' => '<p>Admission into all classes is in progress. Entrance examinations hold every Saturday. Visit the school or apply online today.</p>',
            'values_title' => 'Let your child be part of this exceptional growth system',
            'show_stats' => '1',
            'footer_about' => 'Raising leaders with knowledge, skill and character.',
            'meta_description' => $name . ' — admissions, academics, news, events and gallery.',
            'app_name' => $name . ' App', 'app_version' => '1.0.0',
            'app_intro' => 'Results, fees, attendance, timetable, homework and school news — in your pocket.',
            'app_features' => "Results & report cards\nPay school fees online\nAttendance & timetable\nHomework & e-learning\nAI learning guidance\nNotices & reminders",
            // quick cards under the banner
            'quick1_title' => 'Academics', 'quick1_text' => 'Classes, subjects and how our learners grow.',
            'quick2_title' => 'Admission ongoing', 'quick2_text' => "Start your child's application online today.",
            'quick3_title' => 'Our mobile app', 'quick3_text' => 'Results, fees, attendance and news on your phone.',
            // section headings
            'gallery_title' => 'Life at ' . $name,
            'testimonials_title' => 'What our families say',
            'news_title' => 'From around the school',
            'faq_title' => 'Frequently asked questions',
            'faq_text' => "Can't find what you're looking for? Our admissions team is happy to help.",
            // closing call-to-action band
            'cta_title' => 'Give your child the best start',
            'cta_text' => 'Visit our campus, meet our teachers and see learning in action.',
        ];
        $have = DB::table('website_settings')->pluck('key')->all();
        foreach ($defaults as $k => $v) {
            if (!in_array($k, $have, true)) DB::table('website_settings')->insert(['key' => $k, 'value' => $v, 'created_at' => $now, 'updated_at' => $now]);
        }
        // The first version of the starter data linked to "/admissions/apply", which is POST-only.
        DB::table('website_settings')->where('key', 'hero_button_link')->where('value', '/admissions/apply')->update(['value' => '/admissions#apply', 'updated_at' => $now]);

        // ── home slider: one starter slide, editable under Website › Home slider ──
        if (Schema::hasTable('website_slides') && !DB::table('website_slides')->exists()) {
            DB::table('website_slides')->insert([
                'title' => 'Welcome to ' . $name, 'subtitle' => $motto,
                'button_text' => 'Admission ongoing — apply now', 'button_link' => '/admissions#apply',
                'sort' => 0, 'published' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ── "why us" highlights shown beside the welcome text ──
        if (Schema::hasTable('website_items') && !DB::table('website_items')->where('kind', 'highlight')->exists()) {
            foreach ([
                ['Caring, qualified teachers', 'Small classes where every learner is known by name.', 'ri-user-heart-line'],
                ['Strong academics and character', 'Results that matter, and values that last.', 'ri-medal-line'],
                ['Parents always in the loop', 'Results, fees, attendance and homework on the portal and mobile app.', 'ri-smartphone-line'],
                ['Clubs, sports and creativity', 'Activities every week beyond the classroom.', 'ri-trophy-line'],
            ] as $i => [$title, $body, $icon]) {
                DB::table('website_items')->insert(['kind' => 'highlight', 'title' => $title, 'body' => $body, 'icon' => $icon,
                    'sort' => $i, 'published' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        try { Cache::forget('website.settings.v1'); Cache::forget('website.stats'); Cache::forget('website.menu'); } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // Starter content may have been edited by the admin; nothing is removed.
    }
};

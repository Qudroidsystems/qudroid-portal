<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The public website, managed from the portal: pages built from blocks,
 * products, case studies ("works"), forms and their entries, and an image
 * library. Courses come from the Academy itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 120)->unique();           // "home" is the front page
            $t->string('title', 150);
            $t->string('description', 300)->nullable();  // search engines and link previews
            $t->json('blocks')->nullable();
            $t->string('status', 20)->default('draft');  // draft | published
            $t->boolean('in_nav')->default(false);
            $t->boolean('in_footer')->default(false);
            $t->unsignedInteger('position')->default(0);
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('site_products', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 120)->unique();
            $t->string('name', 120);
            $t->string('tagline', 200)->nullable();
            $t->string('category', 60)->nullable();
            $t->text('summary')->nullable();
            $t->longText('body')->nullable();            // Markdown
            $t->json('features')->nullable();            // [{icon, title, text}]
            $t->string('icon', 60)->nullable();          // Remix icon class
            $t->string('color', 9)->nullable();
            $t->string('image')->nullable();             // public disk path or URL
            $t->string('link_url')->nullable();
            $t->string('link_label', 60)->nullable();
            $t->string('price_label', 80)->nullable();
            $t->boolean('featured')->default(false);
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
        });

        Schema::create('site_works', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 120)->unique();
            $t->string('title', 150);
            $t->string('client', 120)->nullable();
            $t->string('category', 60)->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->text('summary')->nullable();
            $t->longText('body')->nullable();            // Markdown
            $t->json('tags')->nullable();
            $t->json('results')->nullable();             // [{value, label}]
            $t->string('image')->nullable();
            $t->string('color', 9)->nullable();
            $t->string('link_url')->nullable();
            $t->boolean('featured')->default(false);
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
        });

        Schema::create('site_forms', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 120)->unique();
            $t->string('name', 150);
            $t->text('intro')->nullable();
            $t->json('fields');                          // [{key, label, type, required, options, help}]
            $t->string('button_label', 60)->nullable();
            $t->string('success_message', 500)->nullable();
            $t->string('notify', 500)->nullable();       // e-mail addresses, comma separated
            $t->boolean('to_crm')->default(false);       // each entry becomes a CRM deal
            $t->string('status', 20)->default('open');   // open | closed
            $t->timestamps();
        });

        Schema::create('site_form_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('form_id')->constrained('site_forms')->cascadeOnDelete();
            $t->json('data');
            $t->string('name', 150)->nullable();
            $t->string('email', 190)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('status', 20)->default('new');    // new | read | archived
            $t->foreignId('deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
            $t->string('ip_address', 45)->nullable();
            $t->string('page', 300)->nullable();
            $t->timestamps();
            $t->index(['form_id', 'status']);
        });

        Schema::create('site_media', function (Blueprint $t) {
            $t->id();
            $t->string('path');
            $t->string('name', 190);
            $t->string('mime', 100)->nullable();
            $t->unsignedInteger('size')->default(0);
            $t->unsignedSmallInteger('width')->nullable();
            $t->unsignedSmallInteger('height')->nullable();
            $t->string('alt', 200)->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('academy_courses', function (Blueprint $t) {
            // Registering on the website admits the person straight away and takes them to payment.
            $t->boolean('instant_enrollment')->default(false)->after('certificate_enabled');
            $t->string('image')->nullable()->after('instant_enrollment');
        });

        if (Schema::hasTable('permissions')) {
            $guard = config('auth.defaults.guard', 'web');
            if (!DB::table('permissions')->where('name', 'Manage website')->where('guard_name', $guard)->exists()) {
                DB::table('permissions')->insert(['name' => 'Manage website', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
            }
            try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        Schema::table('academy_courses', fn (Blueprint $t) => $t->dropColumn(['instant_enrollment', 'image']));
        Schema::dropIfExists('site_media');
        Schema::dropIfExists('site_form_entries');
        Schema::dropIfExists('site_forms');
        Schema::dropIfExists('site_works');
        Schema::dropIfExists('site_products');
        Schema::dropIfExists('site_pages');
        DB::table('permissions')->where('name', 'Manage website')->delete();
    }
};

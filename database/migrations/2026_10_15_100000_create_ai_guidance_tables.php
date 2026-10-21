<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AI learning & career guidance for students and parents (mobile app).
 *
 * ai_guidance_settings — one row (id = 1): which AI the school uses (or the
 *   built-in rules only), the model, the API key (encrypted), who may see it.
 * ai_guidance_reports — the guidance generated for a student for a term, kept
 *   so it is not regenerated (and paid for) on every visit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_guidance_settings')) {
            Schema::create('ai_guidance_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(true);
                $table->string('provider', 20)->default('rules');      // rules | anthropic | openai | gemini
                $table->string('model', 100)->nullable();
                $table->text('api_key')->nullable();                     // encrypted
                $table->boolean('for_parents')->default(true);
                $table->boolean('for_students')->default(true);
                $table->unsignedSmallInteger('refreshes_per_term')->default(3);
                $table->text('school_context')->nullable();              // e.g. "Nigerian secondary school, WAEC/NECO, JAMB"
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
            DB::table('ai_guidance_settings')->insert([
                'id' => 1, 'enabled' => true, 'provider' => 'rules',
                'school_context' => 'Nigerian secondary school (JSS/SSS). Senior students sit WAEC/NECO and JAMB UTME for admission into Nigerian universities, polytechnics and colleges of education.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (!Schema::hasTable('ai_guidance_reports')) {
            Schema::create('ai_guidance_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_id')->index();
                $table->unsignedBigInteger('term_id')->nullable();
                $table->unsignedBigInteger('session_id')->nullable();
                $table->string('provider', 20);
                $table->string('model', 100)->nullable();
                $table->string('input_hash', 64);
                $table->json('content');
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->timestamps();
                $table->index(['student_id', 'session_id', 'term_id']);
            });
        }

        // Permission for the settings page, given to the admin roles that exist.
        if (Schema::hasTable('permissions')) {
            $now = now();
            $id = DB::table('permissions')->where('name', 'Manage AI guidance')->where('guard_name', 'web')->value('id');
            if (!$id) {
                $row = ['name' => 'Manage AI guidance', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now];
                if (Schema::hasColumn('permissions', 'title')) $row['title'] = 'System';
                $id = DB::table('permissions')->insertGetId($row);
            }
            $roles = DB::table('roles')->whereIn('name', ['Super Admin', 'Admin', 'Administrator'])->pluck('id');
            foreach ($roles as $rid) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $rid]);
            }
            try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_guidance_reports');
        Schema::dropIfExists('ai_guidance_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Workflow automation: "when this happens, if these are true, do these steps".
        Schema::create('workflows', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('description', 500)->nullable();
            $t->string('trigger', 60)->index();
            $t->json('definition');
            $t->boolean('is_active')->default(false);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_run_at')->nullable();
            $t->unsignedInteger('runs_count')->default(0);
            $t->timestamps();
        });

        Schema::create('workflow_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $t->string('status', 20)->default('queued')->index(); // queued, running, waiting, done, stopped, failed, skipped
            $t->nullableMorphs('subject');
            $t->string('title', 200)->nullable();
            $t->json('payload')->nullable();
            $t->unsignedSmallInteger('step')->default(0);
            $t->timestamp('resume_at')->nullable()->index();
            $t->json('log')->nullable();
            $t->string('error', 500)->nullable();
            $t->string('dedupe', 120)->nullable();
            $t->unsignedTinyInteger('depth')->default(0);
            $t->boolean('is_test')->default(false);
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
            $t->unique(['workflow_id', 'dedupe']);
        });

        if (Schema::hasTable('permissions')) {
            $guard = config('auth.defaults.guard', 'web');
            if (!DB::table('permissions')->where('name', 'Manage workflows')->where('guard_name', $guard)->exists()) {
                DB::table('permissions')->insert(['name' => 'Manage workflows', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
            }
            try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflows');
        DB::table('permissions')->where('name', 'Manage workflows')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What parents and students see on result pages (portal and mobile app).
 * One row (id = 1). `fields` holds on/off switches keyed by
 * App\Services\Results\ResultDisplay field keys; missing keys use defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('result_display_settings')) {
            Schema::create('result_display_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('parents_can_view')->default(true);
                $table->boolean('students_can_view')->default(true);
                $table->string('position_basis', 10)->default('cum');   // cum | term
                $table->json('fields')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('permissions')) {
            $now = now();
            $guard = 'web';
            $perm = DB::table('permissions')->where('name', 'Manage result display')->first();
            $permId = $perm->id ?? DB::table('permissions')->insertGetId([
                'name' => 'Manage result display', 'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now,
            ]);
            if (Schema::hasTable('roles') && Schema::hasTable('role_has_permissions')) {
                $roleIds = DB::table('roles')->whereIn('name', ['Super Admin', 'Admin', 'Administrator', 'super-admin', 'admin'])->pluck('id');
                foreach ($roleIds as $rid) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permId, 'role_id' => $rid]);
                }
            }
            try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('result_display_settings');
    }
};

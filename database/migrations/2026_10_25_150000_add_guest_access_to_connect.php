<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Connect, part 7: guests. Clients can be added to chats that are shared
 * with clients; they see only those chats and the people in them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connect_conversations', function (Blueprint $t) {
            $t->boolean('guests_allowed')->default(false)->after('archived_at');
            $t->foreignId('client_project_id')->nullable()->after('guests_allowed')->constrained('projects')->nullOnDelete();
        });

        // Direct chats that already include a client count as shared.
        DB::table('connect_conversations as c')->where('c.type', 'direct')
            ->whereExists(fn ($q) => $q->from('connect_participants as p')->join('users as u', 'u.id', '=', 'p.user_id')
                ->whereColumn('p.conversation_id', 'c.id')->where('u.user_type', 'client'))
            ->update(['guests_allowed' => true]);

        // The permission, given to the Client role (the seeder does the same on fresh installs).
        if (Schema::hasTable('permissions')) {
            $guard = config('auth.defaults.guard', 'web');
            $id = DB::table('permissions')->where('name', 'Chat as a guest')->where('guard_name', $guard)->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => 'Chat as a guest', 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
            $role = DB::table('roles')->where('name', 'Client')->where('guard_name', $guard)->value('id');
            if ($role && !DB::table('role_has_permissions')->where('permission_id', $id)->where('role_id', $role)->exists()) {
                DB::table('role_has_permissions')->insert(['permission_id' => $id, 'role_id' => $role]);
            }
            try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        Schema::table('connect_conversations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('client_project_id');
            $t->dropColumn('guests_allowed');
        });
    }
};

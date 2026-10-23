<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // QR check-in: classes, staff days at office locations, meetings and events (conferences).
        Schema::create('checkin_places', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('address', 255)->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->unsignedInteger('radius_m')->default(150);
            $t->time('work_starts')->default('09:00:00');
            $t->time('work_ends')->default('17:00:00');
            $t->unsignedSmallInteger('grace_minutes')->default(15);
            $t->json('workdays')->nullable(); // [1..7], Monday = 1
            $t->boolean('require_location')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('checkin_events', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 12); // class, staff, meeting, event
            $t->string('title', 200);
            $t->text('description')->nullable();
            $t->nullableMorphs('subject'); // ClassSession or Meeting
            $t->foreignId('place_id')->nullable()->constrained('checkin_places')->nullOnDelete();
            $t->date('day')->nullable(); // staff days: one per place per date
            $t->string('location', 255)->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->unsignedInteger('radius_m')->default(150);
            $t->boolean('require_location')->default(false);
            $t->dateTime('starts_at');
            $t->dateTime('ends_at')->nullable();
            $t->unsignedSmallInteger('opens_minutes')->default(60);  // check-in opens this long before the start
            $t->unsignedSmallInteger('late_minutes')->default(15);   // after the start + this = late
            $t->string('audience', 12)->default('invited'); // invited, staff, students, everyone, registered
            $t->boolean('allow_screen')->default(true);
            $t->boolean('allow_badge')->default(true);
            $t->boolean('public_registration')->default(false);
            $t->string('slug', 80)->nullable()->unique();
            $t->unsignedInteger('capacity')->nullable();
            $t->dateTime('registration_closes_at')->nullable();
            $t->string('status', 12)->default('open'); // open, closed, cancelled
            $t->string('secret', 64);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['kind', 'starts_at']);
            $t->unique(['place_id', 'day']);
        });

        Schema::create('checkin_event_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained('checkin_events')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 12)->default('guest'); // guest, host (hosts can scan)
            $t->timestamps();
            $t->unique(['event_id', 'user_id']);
        });

        Schema::create('checkin_guests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained('checkin_events')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 150);
            $t->string('email', 191)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('organisation', 150)->nullable();
            $t->string('code', 32)->unique();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->index(['event_id', 'email']);
        });

        Schema::create('checkin_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained('checkin_events')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('guest_id')->nullable()->constrained('checkin_guests')->cascadeOnDelete();
            $t->string('status', 10)->default('present'); // present, late
            $t->string('method', 10); // screen, badge, manual
            $t->timestamp('checked_in_at');
            $t->timestamp('checked_out_at')->nullable();
            $t->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->unsignedInteger('distance_m')->nullable();
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->unique(['event_id', 'user_id']);
            $t->unique(['event_id', 'guest_id']);
            $t->index(['user_id', 'checked_in_at']);
        });

        if (Schema::hasTable('permissions')) {
            $guard = config('auth.defaults.guard', 'web');
            $id = fn ($name) => DB::table('permissions')->where('name', $name)->where('guard_name', $guard)->value('id')
                ?? DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]);
            $grant = function (int $perm, array $roles) use ($guard) {
                foreach (DB::table('roles')->whereIn('name', $roles)->where('guard_name', $guard)->pluck('id') as $role) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $perm, 'role_id' => $role]);
                }
            };
            $grant($id('Check in'), ['Managing Director', 'Finance Officer', 'HR Manager', 'Project Manager', 'Sales Officer', 'Staff',
                'Academy Administrator', 'Instructor', 'Student', 'Inventory Officer']);
            $grant($id('Run check-in'), ['Managing Director', 'HR Manager', 'Project Manager', 'Academy Administrator', 'Instructor']);
            $grant($id('Manage check-in places'), ['HR Manager']);
            try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        foreach (['checkin_records', 'checkin_guests', 'checkin_event_user', 'checkin_events', 'checkin_places'] as $t) Schema::dropIfExists($t);
        DB::table('permissions')->whereIn('name', ['Check in', 'Run check-in', 'Manage check-in places'])->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Students check themselves in to class from the app: by scanning the class QR,
 * or by GPS ("I'm here") when they are on campus.
 *
 *  - Each class (cohort) has a campus (a check-in place, with its location and
 *    radius) and a check-in mode: QR, GPS, either, or off (the instructor takes
 *    the register).
 *  - Students get a notification when check-in opens; anyone who hasn't checked
 *    in when the class ends is marked absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_cohorts', function (Blueprint $table) {
            $table->foreignId('place_id')->nullable()->after('instructor_id')->constrained('checkin_places')->nullOnDelete();
            $table->string('checkin_mode', 10)->default('either')->after('place_id');      // off | qr | gps | either
            $table->unsignedSmallInteger('checkin_opens_minutes')->default(15)->after('checkin_mode');
            $table->unsignedSmallInteger('late_after_minutes')->default(15)->after('checkin_opens_minutes');
            $table->boolean('auto_absent')->default(true)->after('late_after_minutes');
        });

        Schema::table('academy_class_sessions', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('checkin_closed_at')->nullable();
        });

        Schema::table('checkin_events', function (Blueprint $table) {
            $table->boolean('allow_gps')->default(false)->after('allow_badge');
        });

        Schema::table('checkin_records', function (Blueprint $table) {
            $table->unsignedInteger('accuracy_m')->nullable()->after('distance_m');
        });
    }

    public function down(): void
    {
        Schema::table('checkin_records', fn (Blueprint $t) => $t->dropColumn('accuracy_m'));
        Schema::table('checkin_events', fn (Blueprint $t) => $t->dropColumn('allow_gps'));
        Schema::table('academy_class_sessions', fn (Blueprint $t) => $t->dropColumn(['reminded_at', 'checkin_closed_at']));
        Schema::table('academy_cohorts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('place_id');
            $table->dropColumn(['checkin_mode', 'checkin_opens_minutes', 'late_after_minutes', 'auto_absent']);
        });
    }
};

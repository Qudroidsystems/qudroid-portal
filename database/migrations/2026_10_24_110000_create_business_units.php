<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tables whose records belong to a business unit. */
    protected array $tables = ['finance_invoices', 'finance_expenses', 'crm_deals', 'projects', 'hr_employees', 'academy_courses'];

    public function up(): void
    {
        Schema::create('business_units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 10)->unique();
            $table->string('color', 7)->default('#405189');
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('business_units')->insert([
            ['name' => 'Qudroid Systems', 'code' => 'QS', 'color' => '#2a78d6', 'description' => 'Software, IT services and client projects.', 'position' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Coding Academy', 'code' => 'QCA', 'color' => '#1baf7a', 'description' => 'Courses, cohorts and student fees.', 'position' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ViteSchool', 'code' => 'VS', 'color' => '#eb6834', 'description' => 'The ViteSchool product and its customers.', 'position' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $systems = DB::table('business_units')->where('code', 'QS')->value('id');
        $academy = DB::table('business_units')->where('code', 'QCA')->value('id');

        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('business_unit_id')->nullable()->constrained('business_units')->nullOnDelete();
            });
        }

        // Existing records: academy courses and their fee invoices go to the Coding Academy, the rest to Qudroid Systems.
        DB::table('academy_courses')->update(['business_unit_id' => $academy]);
        DB::table('finance_invoices')->whereNotNull('enrollment_id')->update(['business_unit_id' => $academy]);
        foreach ($this->tables as $t) {
            DB::table($t)->whereNull('business_unit_id')->update(['business_unit_id' => $systems]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('business_unit_id');
            });
        }
        Schema::dropIfExists('business_units');
    }
};

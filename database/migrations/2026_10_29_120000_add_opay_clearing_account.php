<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** OPay payments land in their own clearing account, like Paystack's. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('finance_accounts')->insertOrIgnore([
            'code' => '1030', 'name' => 'OPay clearing', 'type' => 'asset', 'is_system' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('finance_accounts')->where('code', '1030')->where('is_system', true)->delete();
    }
};

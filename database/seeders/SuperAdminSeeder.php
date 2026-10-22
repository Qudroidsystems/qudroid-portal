<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The first administrator (config/qudroid.php → super_admin, set from .env).
 * Created once; an existing account with that e-mail is left as it is
 * apart from making sure it holds the Super Administrator role.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $cfg = config('qudroid.super_admin');

        $user = User::where('email', strtolower($cfg['email']))->first();

        if (!$user) {
            $user = User::create([
                'name'      => $cfg['name'],
                'email'     => strtolower($cfg['email']),
                'user_type' => User::TYPE_STAFF,
                'job_title' => 'System Administrator',
                'password'  => Hash::make($cfg['password']),
            ]);
            // Choose a real password at first sign-in.
            $user->forceFill(['must_change_password' => true, 'email_verified_at' => now()])->save();
            $this->command?->warn("Super Administrator created: {$user->email} (temporary password from QUDROID_ADMIN_PASSWORD).");
        }

        $user->assignRole(config('qudroid.super_admin_role', 'Super Administrator'));
    }
}

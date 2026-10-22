<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\Api\ApiUserPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

/**
 * Token sign-in for the mobile app (Laravel Sanctum).
 *
 * Same rules as the web login: e-mail, username or phone number; disabled
 * accounts are refused; temporary passwords must be changed first. Each
 * phone gets its own token, so signing out on one phone keeps the others.
 */
class AuthController extends ApiController
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'login'       => 'required|string|max:191',
            'password'    => 'required|string|max:191',
            'device_name' => 'nullable|string|max:120',
            'platform'    => 'nullable|in:android,ios,web',
            'app_version' => 'nullable|string|max:30',
            'push_token'  => 'nullable|string|max:512',
        ]);

        $user = $this->findUser(trim($data['login']));

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return $this->fail('The login details are not correct.', 422, 'invalid_credentials', [
                'login' => ['The login details are not correct.'],
            ]);
        }

        if ($user->is_disabled) {
            return $this->fail('This account has been disabled. Please contact your administrator.', 403, 'account_disabled');
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();

        $token = $user->createToken(($data['device_name'] ?? null) ?: 'Mobile app', ['*']);
        $this->rememberDevice($user->id, $token->accessToken->id, $data);

        return $this->ok([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user'       => ApiUserPresenter::present($user),
        ], 'Signed in.');
    }

    public function me(Request $request)
    {
        return $this->ok(ApiUserPresenter::present($request->user()));
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            if (Schema::hasTable('mobile_devices')) {
                DB::table('mobile_devices')->where('token_id', $token->id)->delete();
            }
            $token->delete();
        }

        return $this->ok(null, 'Signed out.');
    }

    /** Sign out of every phone. */
    public function logoutAll(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();
        if (Schema::hasTable('mobile_devices')) {
            DB::table('mobile_devices')->where('user_id', $user->id)->delete();
        }

        return $this->ok(null, 'Signed out on all devices.');
    }

    /** Change password; also how a temporary password is replaced. */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'current_password' => $user->must_change_password ? 'nullable|string' : 'required|string',
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (!$user->must_change_password && !Hash::check((string) $request->current_password, $user->password)) {
            return $this->fail('Your current password is not correct.', 422, 'invalid_current_password', [
                'current_password' => ['Your current password is not correct.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($request->password), 'must_change_password' => false])->save();

        // Sign out the other phones; keep this one.
        $current = $user->currentAccessToken();
        $user->tokens()->when($current, fn ($q) => $q->where('id', '!=', $current->id))->delete();

        return $this->ok(ApiUserPresenter::present($user->fresh()), 'Password changed.');
    }

    /**
     * E-mails a password-reset link (the same link as the web "Forgot password").
     * Always answers the same way so it cannot reveal which addresses exist.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|max:191']);

        try {
            PasswordBroker::sendResetLink(['email' => strtolower(trim($request->email))]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->ok(null, 'If that address belongs to an account, a reset link has been sent to it.');
    }

    // ── helpers ─────────────────────────────────────────────────────────

    /** E-mail, username or phone number (as on the web login). */
    protected function findUser(string $login): ?User
    {
        if ($login === '') return null;
        if (str_contains($login, '@')) {
            return User::where('email', strtolower($login))->first();
        }
        return User::where('username', $login)->orWhere('phone_number', $login)->first();
    }

    protected function rememberDevice(int $userId, int $tokenId, array $d): void
    {
        if (!Schema::hasTable('mobile_devices')) return;

        $row = [
            'user_id' => $userId, 'token_id' => $tokenId,
            'platform' => $d['platform'] ?? null, 'device_name' => $d['device_name'] ?? null,
            'app_version' => $d['app_version'] ?? null, 'last_seen_at' => now(), 'updated_at' => now(),
        ];

        if (!empty($d['push_token'])) {
            DB::table('mobile_devices')->updateOrInsert(['push_token' => $d['push_token']], $row + ['created_at' => now()]);
        } else {
            DB::table('mobile_devices')->insert($row + ['created_at' => now()]);
        }
    }
}

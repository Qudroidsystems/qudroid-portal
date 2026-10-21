<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SchoolInformation;
use App\Models\User;
use App\Services\Api\ApiUserPresenter;
use App\Services\Messaging\MessagingService;
use App\Services\Parents\ParentAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

/**
 * Token sign-in for the mobile app (Laravel Sanctum).
 *
 * Same rules as the web login: e-mail or phone number (parents sign in with
 * their phone), disabled accounts are refused, temporary passwords must be
 * changed before anything else. Each phone gets its own token, so signing
 * out on one phone does not sign out the others.
 */
class AuthController extends ApiController
{
    /** Shared with the web reset flow (ParentPasswordController). */
    protected const RESET_TTL_MINUTES = 10;
    protected const RESET_MAX_TRIES   = 5;

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

        if (!empty($user->is_disabled)) {
            return $this->fail('This account has been disabled. Please contact the school.', 403, 'account_disabled');
        }

        if (Schema::hasColumn('users', 'last_login_at')) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
        }

        $deviceName = ($data['device_name'] ?? null) ?: 'Mobile app';
        $token = $user->createToken($deviceName, ['*']);

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
     * Parents: text a 6-digit reset code to their phone. Always answers the
     * same way so the endpoint cannot be used to find out which numbers exist.
     */
    public function forgotPassword(Request $request, MessagingService $messaging)
    {
        $request->validate(['phone' => 'required|string|max:20']);
        $answer = $this->ok(null, 'If that number belongs to a parent account, a reset code has been sent to it.');

        $phone = MessagingService::normalizePhone($request->phone);
        $user  = $phone ? ParentAccountService::findByPhone($phone) : null;
        if (!$user || !$user->hasRole(ParentAccountService::ROLE) || !empty($user->is_disabled)) return $answer;
        if (Cache::has($this->resetKey($user) . ':sent')) return $answer;

        $code = (string) random_int(100000, 999999);
        Cache::put($this->resetKey($user), ['hash' => Hash::make($code), 'tries' => 0], now()->addMinutes(self::RESET_TTL_MINUTES));
        Cache::put($this->resetKey($user) . ':sent', 1, now()->addMinute());

        $school = (SchoolInformation::getActiveSchool() ?? SchoolInformation::first())->school_name ?? config('app.name');
        $text = "{$school}: your parent portal reset code is {$code}. It expires in " . self::RESET_TTL_MINUTES . ' minutes. Do not share it.';

        foreach (['sms', 'whatsapp'] as $ch) {
            if (!$messaging->enabled($ch)) continue;
            $r = $messaging->send($ch, $phone, $text, ['name' => $user->name]);
            if (($r['status'] ?? '') === 'sent') break;
            Log::warning('API parent reset code not sent', ['user' => $user->id, 'channel' => $ch, 'error' => $r['error'] ?? null]);
        }

        return $answer;
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'phone'       => 'required|string|max:20',
            'code'        => 'required|digits:6',
            'password'    => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'device_name' => 'nullable|string|max:120',
        ]);

        $wrong = fn () => $this->fail('That code is wrong or has expired. Request a new one.', 422, 'invalid_code', [
            'code' => ['That code is wrong or has expired. Request a new one.'],
        ]);

        $user = ParentAccountService::findByPhone($data['phone']);
        if (!$user || !$user->hasRole(ParentAccountService::ROLE)) return $wrong();

        $entry = Cache::get($this->resetKey($user));
        if (!$entry || $entry['tries'] >= self::RESET_MAX_TRIES) return $wrong();

        if (!Hash::check($data['code'], $entry['hash'])) {
            $entry['tries']++;
            Cache::put($this->resetKey($user), $entry, now()->addMinutes(self::RESET_TTL_MINUTES));
            return $wrong();
        }

        Cache::forget($this->resetKey($user));
        $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();
        $user->tokens()->delete();

        $token = $user->createToken($data['device_name'] ?? 'Mobile app');

        return $this->ok([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user'       => ApiUserPresenter::present($user),
        ], 'Your password has been reset.');
    }

    // ── helpers ─────────────────────────────────────────────────────────

    /** E-mail, or a phone number matched to its account (as on the web login). */
    protected function findUser(string $login): ?User
    {
        if ($login === '') return null;
        if (str_contains($login, '@')) {
            return User::where('email', $login)->first();
        }
        return ParentAccountService::findByPhone($login)
            ?? User::where('email', $login)->first();
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

    protected function resetKey(User $user): string
    {
        return 'parent-reset:' . $user->id;
    }
}

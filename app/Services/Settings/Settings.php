<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Portal-wide settings saved from the admin screens. A saved value wins over
 * the matching .env / config value; with nothing saved, config is used as before.
 *
 * Secret values (API keys) are encrypted with the app key, so changing APP_KEY
 * means entering them again.
 */
class Settings
{
    public const SECRET = ['paystack.test_secret_key', 'paystack.live_secret_key', 'giphy.api_key'];

    protected const CACHE = 'portal-settings';

    /** Raw stored rows, key => value (secrets still encrypted). */
    protected function rows(): array
    {
        try {
            if (($rows = Cache::get(self::CACHE)) !== null) return $rows;
            if (!Schema::hasTable('settings')) return [];   // before migrate: don't remember that

            $rows = Setting::pluck('value', 'key')->all();
            Cache::forever(self::CACHE, $rows);

            return $rows;
        } catch (\Throwable $e) {
            return [];   // database not reachable
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->rows()) && $this->rows()[$key] !== null && $this->rows()[$key] !== '';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) return $default;
        $value = $this->rows()[$key];
        if (in_array($key, self::SECRET, true)) {
            try {
                return Crypt::decryptString($value);
            } catch (DecryptException $e) {
                return $default;   // saved under a different APP_KEY
            }
        }

        return $value;
    }

    public function json(string $key): ?array
    {
        $v = $this->get($key);

        return is_string($v) ? json_decode($v, true) : null;
    }

    /** Save values; null removes the setting (falls back to config). */
    public function put(array $values, ?User $by = null): void
    {
        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                Setting::where('key', $key)->delete();
                continue;
            }
            if (is_array($value)) $value = json_encode($value);
            $value = (string) $value;
            if (in_array($key, self::SECRET, true)) $value = Crypt::encryptString($value);
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $by?->id]);
        }
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE);
    }

    /** Who last changed any of these keys, and when. */
    public function lastChange(array $keys): ?Setting
    {
        try {
            return Setting::with('editor')->whereIn('key', $keys)->latest('updated_at')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Paystack ────────────────────────────────────────────────────────

    public const PAYSTACK = ['paystack.active', 'paystack.mode', 'paystack.test_public_key', 'paystack.test_secret_key', 'paystack.live_public_key', 'paystack.live_secret_key'];

    /** config('services.paystack') with saved values on top. */
    public function paystack(): array
    {
        $c = (array) config('services.paystack', []);
        foreach (self::PAYSTACK as $key) {
            if ($this->has($key)) $c[substr($key, 9)] = $this->get($key);
        }
        if ($this->has('paystack.active')) $c['active'] = $this->get('paystack.active') === '1';

        return $c;
    }

    /** Where the Paystack values come from, for the settings screen. */
    public function paystackSource(): string
    {
        foreach (self::PAYSTACK as $key) if ($this->has($key)) return 'portal';

        return 'env';
    }

    // ── Integrations ────────────────────────────────────────────────────

    /** The GIPHY key for GIFs in chat: saved in the portal, else GIPHY_API_KEY. */
    public function giphyKey(): ?string
    {
        return $this->get('giphy.api_key') ?: (config('services.giphy.key') ?: null);
    }

    // ── Payroll (PAYE) ──────────────────────────────────────────────────

    public const PAYROLL_FIELDS = ['pension_employee', 'pension_employer', 'nhf_rate', 'rent_relief_rate', 'rent_relief_cap', 'working_days_month'];

    /** config('qudroid.payroll') with saved rates on top. */
    public function payroll(): array
    {
        $c = (array) config('qudroid.payroll', []);
        $saved = $this->json('payroll.rates') ?? [];
        foreach (array_merge(self::PAYROLL_FIELDS, ['tax_bands']) as $field) {
            if (array_key_exists($field, $saved)) $c[$field] = $saved[$field];
        }

        return $c;
    }

    /** The accountant's sign-off ['name', 'note', 'at', 'by'], only while it still matches the rates in use. */
    public function payrollReview(): ?array
    {
        $r = $this->json('payroll.review');

        return $r && ($r['hash'] ?? null) === $this->payrollHash() ? $r : null;
    }

    /** A fingerprint of the rates in use, so a sign-off lapses when they change. */
    public function payrollHash(?array $c = null): string
    {
        $c ??= $this->payroll();
        $keep = array_intersect_key($c, array_flip(array_merge(self::PAYROLL_FIELDS, ['tax_bands'])));
        ksort($keep);

        return sha1(json_encode($keep));
    }
}

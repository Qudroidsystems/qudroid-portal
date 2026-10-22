<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * The portal's modules as listed in config/qudroid.php → modules.
 *
 *  - live:    the module's route exists (it has been built and installed)
 *  - allowed: the user may open it (holds its permission, or is Super Administrator)
 */
class Modules
{
    /** @return array<string, array> keyed by module key */
    public static function all($user = null): array
    {
        $user ??= auth()->user();
        $out = [];

        foreach ((array) config('qudroid.modules', []) as $key => $m) {
            $live = !empty($m['route']) && Route::has($m['route']);
            $allowed = $user && (empty($m['permission']) || self::can($user, $m['permission']));
            $out[$key] = $m + [
                'key'     => $key,
                'live'    => $live,
                'allowed' => (bool) $allowed,
                'url'     => $live ? route($m['route']) : null,
            ];
        }

        return $out;
    }

    /** Modules the user can open right now. */
    public static function available($user = null): array
    {
        return array_filter(self::all($user), fn ($m) => $m['live'] && $m['allowed']);
    }

    protected static function can($user, string $permission): bool
    {
        try {
            return $user->can($permission);
        } catch (\Throwable $e) {
            // Permission not created yet (module not installed).
            return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

/**
 * Public: the organisation's name, logo and colour, so the app can brand
 * its sign-in screen before anyone signs in.
 */
class AppConfigController extends ApiController
{
    public function show()
    {
        $b = config('qudroid');

        return $this->ok([
            'name'          => $b['name'],
            'company'       => $b['company'],
            'tagline'       => $b['tagline'],
            'logo_url'      => !empty($b['logo']) ? asset($b['logo']) : null,
            'logo_light_url'=> !empty($b['logo_light']) ? asset($b['logo_light']) : null,
            'primary_color' => $b['primary_color'],
            'website'       => $b['website'],
            'support_email' => $b['support_email'],
            'support_phone' => $b['support_phone'],
            'api_version'   => 1,
            'modules'       => collect($b['modules'] ?? [])->map(fn ($m, $key) => [
                'key' => $key, 'name' => $m['name'], 'phase' => $m['phase'] ?? null,
            ])->values(),
        ]);
    }
}

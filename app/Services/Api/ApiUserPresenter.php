<?php

namespace App\Services\Api;

use App\Models\User;
use App\Support\Modules;

/**
 * The signed-in user as the mobile app sees them (login, /auth/me).
 * `modules` lists the module keys the user can open, so the app shows
 * only the tabs that apply to them.
 */
class ApiUserPresenter
{
    public static function present(User $user): array
    {
        return [
            'id'                   => $user->id,
            'name'                 => $user->name,
            'first_name'           => $user->first_name,
            'email'                => $user->email,
            'username'             => $user->username,
            'phone_number'         => $user->phone_number,
            'user_type'            => $user->user_type,
            'user_type_label'      => $user->user_type_label,
            'job_title'            => $user->job_title,
            'avatar_url'           => $user->avatar_path_url,
            'initials'             => $user->initials,
            'roles'                => $user->getRoleNames()->values(),
            'permissions'          => $user->isSuperAdmin() ? ['*'] : $user->getAllPermissions()->pluck('name')->values(),
            'modules'              => array_keys(Modules::available($user)),
            'must_change_password' => (bool) $user->must_change_password,
        ];
    }
}

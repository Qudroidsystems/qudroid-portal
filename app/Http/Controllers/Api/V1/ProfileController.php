<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Api\ApiUserPresenter;
use Illuminate\Http\Request;

/** The signed-in user's own profile. */
class ProfileController extends ApiController
{
    public function show(Request $request)
    {
        $u = $request->user();

        return $this->ok(ApiUserPresenter::present($u) + [
            'gender'        => $u->gender,
            'date_of_birth' => $u->date_of_birth?->toDateString(),
            'address'       => $u->address,
            'bio'           => $u->bio,
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'member_since'  => $u->created_at?->toDateString(),
        ]);
    }

    public function update(Request $request)
    {
        $u = $request->user();
        $data = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'phone_number'  => 'sometimes|nullable|string|max:30',
            'gender'        => 'sometimes|nullable|in:male,female',
            'date_of_birth' => 'sometimes|nullable|date|before:today',
            'address'       => 'sometimes|nullable|string|max:500',
            'bio'           => 'sometimes|nullable|string|max:2000',
        ]);

        $u->update($data);

        return $this->show($request);
    }
}

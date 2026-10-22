<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Profile settings: anyone can edit their own profile; users with the
 * "Update user" permission can edit other people's.
 */
class ProfileController extends Controller
{
    public function show(Request $request, $id): View
    {
        $user = $this->editable($request, $id);

        return view('users.settings', [
            'user'      => $user,
            'isSelf'    => $user->id === $request->user()->id,
            'pagetitle' => 'Profile Settings',
        ]);
    }

    public function updateInfo(Request $request): RedirectResponse
    {
        $user = $this->editable($request, $request->input('id', $request->user()->id));

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'username'      => 'nullable|string|max:60|alpha_dash|unique:users,username,' . $user->id,
            'phone_number'  => 'nullable|string|max:30',
            'gender'        => 'nullable|in:male,female',
            'date_of_birth' => 'nullable|date|before:today',
            'job_title'     => 'nullable|string|max:120',
            'address'       => 'nullable|string|max:500',
            'bio'           => 'nullable|string|max:2000',
        ]);

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $user = $this->editable($request, $request->input('id', $request->user()->id));
        $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);

        $file = $request->file('avatar');
        $name = 'user_' . $user->id . '_' . time() . '.' . $file->extension();
        $file->storeAs(User::AVATAR_DIR, $name, 'public');

        if ($user->avatar && $user->avatar !== $name) {
            Storage::disk('public')->delete(User::AVATAR_DIR . '/' . basename($user->avatar));
        }
        $user->forceFill(['avatar' => $name])->save();

        return response()->json([
            'success'    => true,
            'message'    => 'Profile photo updated.',
            'avatar_url' => $user->fresh()->avatar_url,
        ]);
    }

    public function updateEmail(Request $request): JsonResponse
    {
        $user = $this->editable($request, $request->input('userid', $request->user()->id));
        $request->validate([
            'emailaddress' => 'required|email|max:255|confirmed|unique:users,email,' . $user->id,
        ]);

        $user->forceFill(['email' => strtolower(trim($request->emailaddress)), 'email_verified_at' => null])->save();

        return response()->json(['success' => true, 'message' => 'E-mail address updated.']);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $this->editable($request, $request->input('userid', $request->user()->id));
        $isSelf = $user->id === $request->user()->id;

        $request->validate([
            // People changing their own password confirm the current one.
            'current_password' => $isSelf && !$user->must_change_password ? 'required|string' : 'nullable|string',
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if ($isSelf && !$user->must_change_password && !Hash::check((string) $request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Your current password is not correct.'], 422);
        }

        // An admin resetting someone else's password makes it temporary.
        $user->forceFill([
            'password'             => Hash::make($request->password),
            'must_change_password' => !$isSelf,
        ])->save();

        return response()->json(['success' => true, 'message' => 'Password updated.']);
    }

    /** The user being edited — themselves, or anyone if they may update users. */
    protected function editable(Request $request, $id): User
    {
        $user = User::findOrFail((int) $id);
        $me = $request->user();

        abort_unless($user->id === $me->id || $me->can('Update user'), 403, 'You can only edit your own profile.');

        return $user;
    }
}

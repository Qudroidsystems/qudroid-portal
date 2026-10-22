<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * "Choose your own password" — shown to anyone holding a temporary password
 * (new accounts, admin resets) before they can use the portal.
 */
class PasswordChangeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(Request $request)
    {
        return view('auth.passwords.change', [
            'temporary' => (bool) $request->user()->must_change_password,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => $user->must_change_password ? 'nullable|string' : 'required|string',
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (!$user->must_change_password && !Hash::check((string) $request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is not correct.']);
        }

        if (Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Choose a password different from the temporary one.']);
        }

        $user->forceFill([
            'password'             => Hash::make($request->password),
            'must_change_password' => false,
        ])->save();

        return redirect()->route('dashboard')->with('success', 'Your password has been changed.');
    }
}

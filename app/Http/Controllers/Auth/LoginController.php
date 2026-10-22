<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/dashboard';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * The login box takes an e-mail address, a username or a phone number.
     */
    protected function credentials(Request $request)
    {
        $login = trim((string) $request->input($this->username()));

        if ($login !== '' && !str_contains($login, '@')) {
            $user = User::where('username', $login)->orWhere('phone_number', $login)->first();
            if ($user) {
                $login = $user->email;
            }
        }

        return ['email' => $login, 'password' => $request->input('password')];
    }

    protected function authenticated(Request $request, $user)
    {
        if (!empty($user->is_disabled)) {
            $this->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages([
                $this->username() => 'This account has been disabled. Please contact your administrator.',
            ]);
        }

        if (!empty($user->must_change_password)) {
            return redirect()->route('password.change');
        }

        if ($intendedUrl = session('intended')) {
            session()->forget('intended');
            if ($this->isValidIntendedUrl($intendedUrl, $request)) {
                return redirect($intendedUrl);
            }
        }

        return redirect()->intended($this->redirectPath());
    }

    protected function isValidIntendedUrl($url, $request): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        return !$host || $host === $request->getHost();
    }

    public function username()
    {
        return 'email';
    }

    public function logout(Request $request)
    {
        $this->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

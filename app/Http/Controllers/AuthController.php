<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.'])->onlyInput('email');
        }

        $ok = Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'));
        if (! $ok) {
            RateLimiter::hit($key, 300);
            return back()->withErrors(['email' => 'Email or password is wrong, or the account is switched off.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        User::whereKey(Auth::id())->update(['last_login_at' => now()]);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function showPassword()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers(), 'different:current_password'],
        ]);
        $user = $request->user();
        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        return redirect()->route('dashboard')->with('success', 'Password changed.');
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate(['profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'], 'remove_image' => ['nullable', 'boolean']]);
        $user = $request->user();
        $user->use_gravatar = $request->boolean('use_gravatar');

        if ($request->hasFile('profile_image')) {
            $this->forgetImage($user);
            $user->profile_image_path = $request->file('profile_image')->store('avatars', 'local');
        } elseif ($request->boolean('remove_image')) {
            $this->forgetImage($user);
            $user->profile_image_path = null;
        }
        $user->save();

        return redirect()->route('account.password')->with('success', 'Profile saved.');
    }

    public function avatar(User $user)
    {
        abort_unless($user->profile_image_path && Storage::disk('local')->exists($user->profile_image_path), 404);

        return Storage::disk('local')->response($user->profile_image_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    private function forgetImage(User $user): void
    {
        if ($user->profile_image_path) {
            Storage::disk('local')->delete($user->profile_image_path);
        }
    }
}

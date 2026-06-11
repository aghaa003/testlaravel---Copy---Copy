<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LoginLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const ALLOWED_PROVIDERS = ['google', 'github'];

    public function redirect(string $provider): RedirectResponse
    {
        if (! in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            abort(404);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        if (! in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            abort(404);
        }

        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            return redirect($frontendUrl.'/sign-in?social_error=oauth_failed');
        }

        $email = $socialUser->getEmail();
        $socialId = $socialUser->getId();
        $name = $socialUser->getName() ?: $socialUser->getNickname() ?: 'مستخدم';

        if (! $email) {
            return redirect($frontendUrl.'/sign-in?social_error=no_email');
        }

        $user = User::where('provider', $provider)
            ->where('provider_id', $socialId)
            ->first();

        if (! $user) {
            $user = User::where('email', $email)->first();

            if ($user) {
                $user->provider = $provider;
                $user->provider_id = $socialId;
                $user->save();
            }
        }

        if (! $user) {
            $user = DB::transaction(function () use ($email, $name, $provider, $socialId) {
                return User::create([
                    'id' => Str::uuid(),
                    'name' => $name,
                    'username' => $this->generateUsername($email, $name),
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'avatar_url' => null,
                    'role' => 'user',
                    'banned' => false,
                    'points' => 0,
                    'provider' => $provider,
                    'provider_id' => $socialId,
                ]);
            });
        }

        if ($user->banned) {
            return redirect($frontendUrl.'/sign-in?social_error=banned');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $nameParts = explode(' ', $user->name, 2);
        LoginLogRecorder::record(
            $request,
            $user->email,
            'login',
            $user->id,
            $nameParts[0] ?? null,
        );

        return redirect($frontendUrl.'/');
    }

    private function generateUsername(string $email, string $name): string
    {
        $base = Str::slug(explode('@', $email)[0], '_');

        if ($base === '') {
            $base = Str::slug($name, '_');
        }

        if ($base === '') {
            $base = 'user';
        }

        $username = $base;
        $suffix = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base.'_'.$suffix;
            $suffix++;
        }

        return $username;
    }
}

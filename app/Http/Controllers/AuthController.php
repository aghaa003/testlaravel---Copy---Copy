<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LoginLogRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|alpha_dash|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $nameParts = explode(' ', $validated['name'], 2);

        $user = User::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => $validated['name'],
            'username' => $validated['username'] ?? explode('@', $validated['email'])[0],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'points' => 0,
        ]);

        Auth::login($user);

        LoginLogRecorder::record(
            $request,
            $user->email,
            'register',
            $user->id,
            $nameParts[0] ?? null,
        );

        return response()->json([
            'user' => $this->formatUser($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);
        // Brute-force lockout — 10 attempts per identifier+IP per 15 minutes
        $lockKey = 'login_fails_'.sha1($credentials['email'].'|'.$request->ip());
        if (\Illuminate\Support\Facades\Cache::get($lockKey, 0) >= 10) {
            return response()->json([
                'message' => 'تم تجاوز الحد المسموح من المحاولات. يرجى المحاولة بعد 15 دقيقة.',
            ], 429);
        }
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            Cache::put($lockKey, Cache::get($lockKey, 0) + 1, now()->addMinutes(15));
            LoginLogRecorder::record(
                $request,
                $credentials['email'],
                'login_failed',
            );
            throw ValidationException::withMessages([
                'email' => ['بيانات الاعتماد المدخلة غير صحيحة.'],
            ]);
        }

        // Successful login — clear the failed-attempt counter.
        Cache::forget($lockKey);

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->banned || $user->disabled) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => [$user->banned ? 'تم حظر هذا الحساب.' : 'تم تعطيل هذا الحساب.'],
            ]);
        }

        $nameParts = explode(' ', $user->name, 2);

        LoginLogRecorder::record(
            $request,
            $user->email,
            'login',
            $user->id,
            $nameParts[0] ?? null,
        );

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }

    private function formatUser(User $user): array
    {
        $nameParts = explode(' ', $user->name, 2);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'firstName' => $nameParts[0] ?? '',
            'lastName' => $nameParts[1] ?? '',
            'fullName' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'imageUrl' => $user->avatar_url ?? '',
            'avatar_url' => $user->avatar_url,
            'bio' => $user->bio,
            'github_url' => $user->github_url,
            'linkedin_url' => $user->linkedin_url,
            'website_url' => $user->website_url,
            'phone' => $user->phone,
            'country' => $user->country,
            'skills' => $user->skills ?? [],
            'role' => $user->role,
            'banned' => $user->banned,
            'points' => $user->points,
            'global_rank' => User::where('points', '>', $user->points)->count() + 1,
            'createdAt' => $user->created_at?->timestamp * 1000,
            'publicMetadata' => ['role' => $user->role],
        ];
    }
}

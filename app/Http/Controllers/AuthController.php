<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return response()->json($this->formatUser($user), 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الاعتماد المدخلة غير صحيحة.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json($this->formatUser(Auth::user()));
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

    // ✅ Single place that shapes the user for the frontend
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
            'skills' => $user->skills ?? [],
            'role' => $user->role,
            'banned' => $user->banned,
            'points' => $user->points,
            'global_rank' => $user->global_rank,
            'createdAt' => $user->created_at?->timestamp * 1000,
            'publicMetadata' => [],
        ];
    }
}

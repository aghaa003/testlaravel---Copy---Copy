<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    // 1. جلب قائمة المستخدمين
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $total = $query->count();
        $users = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'users' => $users,
            'total' => $total,
        ]);
    }

    // 2. إنشاء مستخدم جديد
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users',
            'email' => 'required|email|unique:users',
            'avatar_url' => 'nullable|url',
            'role' => 'in:user,creator,employer,admin',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $validated['id'] = Str::uuid();
        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    // 3. عرض الملف الشخصي العام لمستخدم معين (PublicProfilePage)
    public function show(User $user)
    {
        $user->loadCount([
            'submissions as solvedChallenges' => function ($query) {
                $query->where('success', true);
            },
            'courses as totalCourses',
            'repositories as totalRepositories',
        ]);

        return response()->json($user);
    }

    // 4. تحديث بيانات مستخدم معين (Admin use)
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,'.$user->id,
            'bio' => 'sometimes|nullable|string',
            'avatar_url' => 'sometimes|nullable|string',
            'github_url' => 'sometimes|nullable|url',
            'linkedin_url' => 'sometimes|nullable|url',
            'website_url' => 'sometimes|nullable|url',
            'skills' => 'sometimes|nullable|array',
        ]);

        $user->update($validated);

        return response()->json($user);
    }

    // 5. لوحة الشرف
    public function leaderboard(Request $request)
    {
        $limit = $request->input('limit', 10);

        $leaderboard = User::orderBy('points', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($user, $index) {
                return [
                    'rank' => $index + 1,
                    'user' => $user,
                    'points' => $user->points,
                    'solvedChallenges' => $user->submissions()->where('success', true)->count(),
                ];
            });

        return response()->json($leaderboard);
    }

    // 6. جلب بيانات المستخدم الحالي (ProfilePage - GET /api/users/profile)
    public function profile(Request $request)
    {
        $user = $request->user();

        $user->loadCount([
            'submissions as solvedChallenges' => function ($query) {
                $query->where('success', true);
            },
            'courses as totalCourses',
            'repositories as totalRepositories',
        ]);

        return response()->json($user);
    }

    // 7. تحديث بيانات المستخدم الحالي (ProfilePage - PUT /api/users/profile)
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,'.$user->id,
            'bio' => 'sometimes|nullable|string',
            'avatar_url' => 'sometimes|nullable|string',
            'github_url' => 'sometimes|nullable|url',
            'linkedin_url' => 'sometimes|nullable|url',
            'website_url' => 'sometimes|nullable|url',
            'skills' => 'sometimes|nullable|array',
        ]);

        $user->update($validated);

        return response()->json($user);
    }
}

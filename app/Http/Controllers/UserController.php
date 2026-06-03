<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
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

    public function update(Request $request, User $user)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,'.$user->id,
            'bio' => 'sometimes|nullable|string',
            'avatar_url' => 'sometimes|nullable|string',
            'github_url' => 'sometimes|nullable|url',
            'linkedin_url' => 'sometimes|nullable|url',
            'website_url' => 'sometimes|nullable|url',
            'skills' => 'sometimes|nullable|array',
            'role' => 'sometimes|in:user,creator,employer,admin',
            'banned' => 'sometimes|boolean',
        ]);

        $user->update($validated);

        return response()->json($user);
    }

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

        return response()->json($this->formatProfileUser($user));
    }

    /**
     * POST /api/users/points — Add points to the authenticated user (e.g. after challenge/activity).
     */
    public function addPoints(Request $request)
    {
        $validated = $request->validate([
            'points' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $user->increment('points', $validated['points']);

        return response()->json([
            'success' => true,
            'totalPoints' => $user->fresh()->points,
        ]);
    }

    /**
     * PUT or POST /api/users/profile — SPA ProfilePage uses POST with firstName, lastName, avatarUrl, bio.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'firstName' => 'sometimes|string|max:255',
            'lastName' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|unique:users,username,'.$user->id,
            'bio' => 'sometimes|nullable|string',
            'avatar_url' => 'sometimes|nullable|string',
            'avatarUrl' => 'sometimes|nullable|string',
            'github_url' => 'sometimes|nullable|url',
            'linkedin_url' => 'sometimes|nullable|url',
            'website_url' => 'sometimes|nullable|url',
            'skills' => 'sometimes|nullable|array',
            'phone' => 'sometimes|nullable|string|max:50',
            'country' => 'sometimes|nullable|string|max:100',
        ]);

        $updates = [];

        if ($request->has('firstName') || $request->has('lastName')) {
            $parts = explode(' ', $user->name, 2);
            $first = $request->input('firstName', $parts[0] ?? '');
            $last = $request->input('lastName', $parts[1] ?? '');
            $updates['name'] = trim($first.' '.$last) ?: $user->name;
        }

        foreach (['name', 'username', 'bio', 'github_url', 'linkedin_url', 'website_url', 'skills'] as $field) {
            if ($request->has($field)) {
                $updates[$field] = $request->input($field);
            }
        }

        if ($request->has('avatarUrl')) {
            $updates['avatar_url'] = $request->input('avatarUrl');
        } elseif ($request->has('avatar_url')) {
            $updates['avatar_url'] = $request->input('avatar_url');
        }

        if ($updates !== []) {
            $user->update($updates);
            $user->refresh();
        }

        $formatted = $this->formatProfileUser($user);

        return response()->json([
            'success' => true,
            'user' => $formatted,
            'profile' => [
                'bio' => $user->bio,
                'avatarUrl' => $user->avatar_url ?? '',
            ],
        ]);
    }

    private function formatProfileUser(User $user): array
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
            'points' => $user->points,
            'global_rank' => $user->global_rank,
            'solvedChallenges' => $user->solved_challenges_count ?? null,
            'totalCourses' => $user->total_courses_count ?? null,
            'totalRepositories' => $user->total_repositories_count ?? null,
        ];
    }
}

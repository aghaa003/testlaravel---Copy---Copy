<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Role (admin) enforced by `role:` middleware in routes/api.php.

        // Include soft-deleted users so an admin can see + restore a mistakenly
        // deleted account. Each row carries `deleted_at` (null if active).
        $query = User::withTrashed();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $total = $query->count();
        $users = $query->orderByRaw('deleted_at IS NOT NULL') // active first
            ->skip($offset)->take($limit)->get();

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
            'submissions as totalSubmissions',
            // ✅ Fixed: "totalCourses" on a public profile means courses this
            // learner has completed, not courses they authored (creator_id) —
            // the old relation always returned 0 for regular students.
            'enrollments as totalCourses' => function ($query) {
                $query->where('completed', true);
            },
            'repositories as totalRepositories',
        ]);

        $globalRank = User::where('points', '>', $user->points)->count() + 1;

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatarUrl' => $user->avatar_url,
            'avatar_url' => $user->avatar_url,
            'bio' => $user->bio,
            'role' => $user->role,
            'points' => $user->points,
            'globalRank' => $globalRank,
            'createdAt' => $user->created_at,
            'solvedChallenges' => $user->solvedChallenges ?? 0,
            'totalCourses' => $user->totalCourses ?? 0,
            'totalRepositories' => $user->totalRepositories ?? 0,
            'totalSubmissions' => $user->totalSubmissions ?? 0,
            'challengeCategories' => [],
        ]);
    }

    /**
     * GET /api/users/{user}/courses — public list of courses this user is enrolled in, with progress %.
     */
    public function courses(User $user)
    {
        $enrollments = $user->enrollments()
            ->with('course')
            ->get()
            ->filter(fn ($e) => $e->course !== null)
            ->map(function ($enrollment) {
                return [
                    'id' => $enrollment->course->id,
                    'title' => $enrollment->course->title,
                    'thumbnailUrl' => $enrollment->course->thumbnail_url,
                    'progress' => $enrollment->progress,
                    'completed' => $enrollment->completed,
                ];
            })
            ->values();

        return response()->json($enrollments);
    }

    public function update(Request $request, User $user)
    {
        // Role (admin) enforced by `role:` middleware in routes/api.php.

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
            'enrollments as totalCourses' => function ($query) {
                $query->where('completed', true);
            },
            'repositories as totalRepositories',
        ]);

        return response()->json($this->formatProfileUser($user));
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

        foreach (['name', 'username', 'bio', 'github_url', 'linkedin_url', 'website_url', 'skills', 'phone', 'country'] as $field) {
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
            'phone' => $user->phone,
            'country' => $user->country,
            'skills' => $user->skills ?? [],
            'role' => $user->role,
            'points' => $user->points,
            'global_rank' => User::where('points', '>', $user->points)->count() + 1,
            'solvedChallenges' => $user->solved_challenges_count ?? null,
            'totalCourses' => $user->total_courses_count ?? null,
            'totalRepositories' => $user->total_repositories_count ?? null,
        ];
    }

    /**
     * DELETE /api/users/me — self-service account deletion.
     * Soft-deletes the account (same mechanism as admin destroy): deleted_at is
     * set, Auth can no longer resolve the user, and the current session is
     * invalidated so the user is logged out immediately. Profile data,
     * submissions, etc. are preserved (not erased) for record-keeping.
     */
    public function deleteSelf(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return response()->json(['error' => 'لا يمكن حذف آخر مدير في النظام.'], 400);
        }

        DB::transaction(function () use ($user) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete(); // soft delete (sets deleted_at)
        });

        \App\Support\AuditLogger::log($request, 'self_delete_account', 'User', $user->id, ['email' => $user->email]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true, 'message' => 'تم حذف حسابك بنجاح']);
    }

    // GET /api/check-availability?email=x&username=y
    public function checkAvailability(Request $request)
    {
        $result = ['email' => null, 'username' => null];

        if ($request->has('email')) {
            $result['email'] = User::where('email', $request->email)->exists()
                ? 'taken'
                : 'available';
        }

        if ($request->has('username')) {
            $result['username'] = User::where('username', $request->username)->exists()
                ? 'taken'
                : 'available';
        }

        return response()->json($result);
    }
}

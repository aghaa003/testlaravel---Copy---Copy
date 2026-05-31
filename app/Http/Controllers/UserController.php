<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // 1. جلب قائمة المستخدمين (تطابق UserListResponse)
    public function index(Request $request)
    {
        $query = User::query();

        // تصفية حسب الدور (role) إذا تم تمريره من الـ React
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        // حساب إجمالي العدد وجلب البيانات بناءً على limit و offset
        $total = $query->count();
        $users = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'users' => $users,
            'total' => $total,
        ]);
    }

    // 2. إنشاء مستخدم جديد (عند التسجيل)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'username' => 'required|string|unique:users',
            'email' => 'required|email|unique:users',
            'avatar_url' => 'nullable|url',
            'role' => 'in:user,creator,employer,admin',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    // 3. عرض الملف الشخصي لمستخدم معين (تطابق UserProfile)
    public function show(User $user)
    {
        // جلب الإحصائيات المطلوبة للملف الشخصي (عدد الدورات، التحديات، والمشاريع)
        $user->loadCount([
            'submissions as solvedChallenges' => function ($query) {
                $query->where('success', true);
            },
            'courses as totalCourses',
            'repositories as totalRepositories',
        ]);

        return response()->json($user);
    }

    // 4. تحديث بيانات المستخدم
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'bio' => 'sometimes|nullable|string',
            'avatar_url' => 'sometimes|nullable|url',
        ]);

        $user->update($validated);

        return response()->json($user);
    }

    // 5. لوحة الشرف (Leaderboard)
    public function leaderboard(Request $request)
    {
        $limit = $request->input('limit', 10);

        // ترتيب المستخدمين حسب النقاط تنازلياً
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
}

<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    // 1. إحصائيات المنصة العامة (تطابق PlatformStats)
    public function platform()
    {
        $totalUsers = User::count();
        $totalCourses = Course::count();
        $totalChallenges = Challenge::count();
        $totalSubmissions = ChallengeSubmission::count();
        $successfulSubmissions = ChallengeSubmission::where('success', true)->count();

        return response()->json([
            'totalUsers' => $totalUsers,
            'totalCourses' => $totalCourses,
            'totalChallenges' => $totalChallenges,
            'totalSubmissions' => $totalSubmissions,
            'successRate' => $totalSubmissions > 0 ? ($successfulSubmissions / $totalSubmissions) * 100 : 0,
        ]);
    }

    // 2. إحصائيات مستخدم معين (تطابق الهيكل الدقيق لـ UserStats في api.schemas.ts)
    public function userStats(User $user)
    {
        // حساب التحديات المحلولة بنجاح
        $solvedChallenges = $user->submissions()->where('success', true)->count();

        // حساب المشاريع التي قام بإنشائها
        $repositoriesCreated = $user->repositories()->count();

        // محاكاة حساب الدورات المسجل بها (يمكنك ربطها بجدول enrollments لاحقاً إن وُجد)
        $coursesEnrolled = DB::table('reviews')->where('user_id', $user->id)->distinct('course_id')->count();

        // تفصيل التحديات المحلولة حسب الفئة (Categories Breakdown)
        $categoriesBreakdown = ChallengeSubmission::join('challenges', 'challenge_submissions.challenge_id', '=', 'challenges.id')
            ->where('challenge_submissions.user_id', $user->id)
            ->where('challenge_submissions.success', true)
            ->select('challenges.category', DB::raw('count(*) as count'))
            ->groupBy('challenges.category')
            ->get();

        return response()->json([
            'userId' => $user->id,
            'solvedChallenges' => $solvedChallenges,
            'totalPoints' => $user->points,
            'globalRank' => $user->global_rank ?? 0,
            'coursesEnrolled' => $coursesEnrolled,
            'repositoriesCreated' => $repositoriesCreated,
            'categoriesBreakdown' => $categoriesBreakdown,
        ]);
    }
}

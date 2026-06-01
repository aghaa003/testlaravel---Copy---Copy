<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function platform()
    {
        $totalUsers = User::count();
        $totalCourses = Course::count();
        $totalChallenges = Challenge::count();
        $totalSubmissions = ChallengeSubmission::count();
        $successful = ChallengeSubmission::where('success', true)->count();

        return response()->json([
            'totalUsers' => $totalUsers,
            'totalCourses' => $totalCourses,
            'totalChallenges' => $totalChallenges,
            'totalSubmissions' => $totalSubmissions,
            'successRate' => $totalSubmissions > 0
                                    ? ($successful / $totalSubmissions) * 100
                                    : 0,
        ]);
    }

    public function userStats(User $user)
    {
        $solvedChallenges = $user->submissions()->where('success', true)->count();
        $repositoriesCreated = $user->repositories()->count();

        // ✅ Fixed: now uses enrollments table
        $coursesEnrolled = DB::table('enrollments')
            ->where('user_id', $user->id)
            ->count();

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

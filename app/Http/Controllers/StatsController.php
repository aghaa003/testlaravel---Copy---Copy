<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Course;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function platform()
    {
        // Served from cache (warmed hourly by academy:refresh-derived-data); falls
        // back to a live 5-minute computation if the cache is cold.
        $stats = Cache::remember('stats.platform', now()->addMinutes(5), function () {
            $totalSubmissions = ChallengeSubmission::count();
            $successful = ChallengeSubmission::where('success', true)->count();

            return [
                'totalUsers' => User::count(),
                'totalCourses' => Course::count(),
                'totalChallenges' => Challenge::count(),
                'totalRepositories' => Repository::where('visibility', 'public')->count(),
                'totalSubmissions' => $totalSubmissions,
                'totalChallengesSolved' => $successful,
                'successRate' => $totalSubmissions > 0 ? ($successful / $totalSubmissions) * 100 : 0,
            ];
        });

        return response()->json($stats);
    }

    public function userStats(User $user)
    {
        $solvedChallenges = $user->submissions()->where('success', true)->count();
        $repositoriesCreated = $user->repositories()->count();

        $coursesEnrolled = DB::table('enrollments')
            ->where('user_id', $user->id)
            ->count();

        $coursesCompleted = DB::table('enrollments')
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->count();

        $videoWatchedSeconds = (int) DB::table('lesson_progress')
            ->where('user_id', $user->id)
            ->sum('watched_seconds');

        $attemptedChallenges = ChallengeSubmission::where('user_id', $user->id)
            ->distinct('challenge_id')
            ->count('challenge_id');

        $challengesInProgress = max(0, $attemptedChallenges - $solvedChallenges);

        $categoriesBreakdown = ChallengeSubmission::join('challenges', 'challenge_submissions.challenge_id', '=', 'challenges.id')
            ->where('challenge_submissions.user_id', $user->id)
            ->where('challenge_submissions.success', true)
            ->select('challenges.category', DB::raw('count(*) as count'))
            ->groupBy('challenges.category')
            ->get();

        return response()->json([
            'userId' => $user->id,
            'solvedChallenges' => $solvedChallenges,
            'challengesInProgress' => $challengesInProgress,
            'totalPoints' => $user->points,
            'globalRank' => \App\Models\User::where('points', '>', $user->points)->count() + 1,
            'coursesEnrolled' => $coursesEnrolled,
            'coursesCompleted' => $coursesCompleted,
            'videoWatchedSeconds' => $videoWatchedSeconds,
            'repositoriesCreated' => $repositoriesCreated,
            'categoriesBreakdown' => $categoriesBreakdown,
        ]);
    }
}

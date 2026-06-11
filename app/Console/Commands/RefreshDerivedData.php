<?php

namespace App\Console\Commands;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Course;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RefreshDerivedData extends Command
{
    protected $signature = 'academy:refresh-derived-data';

    protected $description = 'Recompute derived/cached data: challenge success rates, platform stats, leaderboard; prune expired tokens.';

    public function handle(): int
    {
        $this->recomputeChallengeSuccessRates();
        $this->recomputeCourseCounters();
        $this->warmCaches();
        $this->pruneExpiredTokens();

        $this->info('Derived data refreshed.');

        return self::SUCCESS;
    }

    /**
     * Recompute cached course counters (enrollments, lessons) from source tables.
     */
    private function recomputeCourseCounters(): void
    {
        $enrollments = DB::table('enrollments')
            ->select('course_id', DB::raw('COUNT(*) as c'))
            ->groupBy('course_id')->pluck('c', 'course_id');

        $lessons = DB::table('lessons')
            ->select('course_id', DB::raw('COUNT(*) as c'))
            ->groupBy('course_id')->pluck('c', 'course_id');

        foreach (\App\Models\Course::all() as $course) {
            $course->update([
                'total_enrollments' => (int) ($enrollments[$course->id] ?? 0),
                'total_lessons' => (int) ($lessons[$course->id] ?? 0),
            ]);
        }
    }

    /**
     * Recompute success_rate + total_submissions per challenge from the source of truth.
     * Self-heals any drift in the cached counters.
     */
    private function recomputeChallengeSuccessRates(): void
    {
        $stats = ChallengeSubmission::query()
            ->select('challenge_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(success = 1) as solved'))
            ->groupBy('challenge_id')
            ->get()
            ->keyBy('challenge_id');

        foreach (Challenge::all() as $challenge) {
            $row = $stats->get($challenge->id);
            $total = (int) ($row->total ?? 0);
            $solved = (int) ($row->solved ?? 0);
            $challenge->update([
                'total_submissions' => $total,
                'success_rate' => $total > 0 ? round(($solved / $total) * 100, 2) : 0,
            ]);
        }
    }

    /**
     * Warm the caches read by the public stats/leaderboard endpoints.
     */
    private function warmCaches(): void
    {
        $submissions = ChallengeSubmission::count();
        $successful = ChallengeSubmission::where('success', true)->count();

        Cache::put('stats.platform', [
            'totalUsers' => User::count(),
            'totalCourses' => Course::count(),
            'totalChallenges' => Challenge::count(),
            'totalRepositories' => Repository::where('visibility', 'public')->count(),
            'totalSubmissions' => $submissions,
            'totalChallengesSolved' => $successful,
            'successRate' => $submissions > 0 ? ($successful / $submissions) * 100 : 0,
        ], now()->addHours(2));

        Cache::put('leaderboard.top', User::orderBy('points', 'desc')->take(50)->get(), now()->addHours(2));
    }

    /**
     * Remove expired password-reset tokens (older than 60 minutes).
     */
    private function pruneExpiredTokens(): void
    {
        if (DB::getSchemaBuilder()->hasTable('password_reset_tokens')) {
            DB::table('password_reset_tokens')
                ->where('created_at', '<', now()->subMinutes(60))
                ->delete();
        }
    }
}

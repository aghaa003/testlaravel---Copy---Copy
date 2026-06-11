<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnrollmentController extends Controller
{
    /**
     * GET /api/enrollments
     * Get user's enrolled courses with progress
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $enrollments = $user->enrollments()
            ->with('course.lessons')
            ->get()
            ->filter(fn ($enrollment) => $enrollment->course !== null)
            ->map(function ($enrollment) use ($user) {
                $lessonIds = $enrollment->course->lessons->pluck('id');

                $lastProgress = LessonProgress::where('user_id', $user->id)
                    ->whereIn('lesson_id', $lessonIds)
                    ->orderBy('updated_at', 'desc')
                    ->first();

                $lastLessonId = $lastProgress->lesson_id ?? $enrollment->course->lessons->first()?->id;

                return [
                    'id' => $enrollment->id,
                    'course' => $enrollment->course,
                    'progress' => $enrollment->progress,
                    'completed' => $enrollment->completed,
                    'enrolledAt' => $enrollment->created_at,
                    'lastLessonId' => $lastLessonId,
                    'lastWatchedSeconds' => $lastProgress->watched_seconds ?? 0,
                ];
            })
            ->values();

        return response()->json([
            'enrollments' => $enrollments,
            'total' => $enrollments->count(),
        ]);
    }

    /**
     * POST /api/courses/{course}/enroll
     * Enroll user in a course
     */
    public function enroll(Request $request, Course $course)
    {
        $user = Auth::user();

        // Check if already enrolled
        $existing = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'أنت مسجل بالفعل في هذه الدورة',
                'enrollment' => $existing,
            ], 409);
        }

        // Create new enrollment + keep the cached course counter in sync.
        $enrollment = \Illuminate\Support\Facades\DB::transaction(function () use ($user, $course) {
            $e = Enrollment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'progress' => 0,
                'completed' => false,
            ]);
            $course->increment('total_enrollments');

            return $e;
        });

        return response()->json([
            'message' => 'تم تسجيلك في الدورة بنجاح',
            'enrollment' => $enrollment->load('course'),
        ], 201);
    }

    /**
     * POST /api/courses/{course}/progress
     * Update enrollment progress
     */
    public function updateProgress(Request $request, Course $course)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'progress' => 'required|integer|min:0|max:100',
        ]);

        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $enrollment->update([
            'progress' => $validated['progress'],
            'completed' => $validated['progress'] === 100,
        ]);

        return response()->json([
            'message' => 'تم تحديث التقدم',
            'enrollment' => $enrollment,
        ]);
    }

    // GET /api/courses/{course}/progress
    public function getProgress(Request $request, Course $course)
    {
        $user = Auth::user();

        // ✅ Fix Warn 2: return empty array if not enrolled instead of 404
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (! $enrollment) {
            return response()->json([]);
        }

        $lessonsProgress = LessonProgress::where('user_id', $user->id)
            ->whereHas('lesson', fn ($q) => $q->where('course_id', $course->id))
            ->get();

        // ✅ Fix 8: return flat array with camelCase keys CourseWatchPage expects
        $rows = $lessonsProgress->map(fn ($lp) => [
            'lessonId' => $lp->lesson_id,
            'completed' => (bool) $lp->completed,
            'watchedSeconds' => $lp->watched_seconds ?? 0,
        ]);

        return response()->json($rows);
    }

    /**
     * GET /api/courses/{course}/viewers
     * List the users enrolled in / watching the course, with completed-lesson counts.
     * Only the course owner, employers, or admins may view this.
     */
    public function getViewers(Request $request, Course $course)
    {
        $user = Auth::user();
        if ($user->id !== $course->creator_id && ! in_array($user->role, ['employer', 'admin'], true)) {
            return response()->json(['error' => 'غير مصرح'], 403);
        }

        $lessonIds = $course->lessons()->pluck('id');

        $viewers = Enrollment::where('course_id', $course->id)
            ->with('user:id,name,avatar_url')
            ->get()
            ->map(function (Enrollment $e) use ($lessonIds) {
                $completed = $lessonIds->isEmpty() ? 0 : LessonProgress::where('user_id', $e->user_id)
                    ->whereIn('lesson_id', $lessonIds)
                    ->where('completed', true)
                    ->count();

                return [
                    'userId' => $e->user_id,
                    'userName' => $e->user?->name ?? 'مستخدم',
                    'userAvatar' => $e->user?->avatar_url,
                    'completedLessons' => $completed,
                    'progress' => $e->progress,
                ];
            })
            ->values();

        return response()->json($viewers);
    }
}

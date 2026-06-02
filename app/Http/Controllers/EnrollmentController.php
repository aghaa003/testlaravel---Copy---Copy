<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
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
            ->with('course')
            ->get()
            ->map(function ($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'course' => $enrollment->course,
                    'progress' => $enrollment->progress,
                    'completed' => $enrollment->completed,
                    'enrolledAt' => $enrollment->created_at,
                ];
            });

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

        // Create new enrollment
        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'progress' => 0,
            'completed' => false,
        ]);

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

    /**
     * GET /api/courses/{course}/progress
     * Get user's progress in a course
     */
    public function getProgress(Request $request, Course $course)
    {
        $user = Auth::user();

        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        // Load lesson progress
        $lessonsProgress = $user->lessonProgress()
            ->whereHas('lesson', fn ($q) => $q->where('course_id', $course->id))
            ->with('lesson')
            ->get();

        return response()->json([
            'enrollment' => $enrollment,
            'lessonsProgress' => $lessonsProgress,
            'totalLessons' => $course->lessons()->count(),
            'completedLessons' => $lessonsProgress->where('completed', true)->count(),
        ]);
    }

    /**
     * GET /api/courses/{course}/viewers
     * Get number of users viewing/enrolled in course
     */
    public function getViewers(Request $request, Course $course)
    {
        $enrolledCount = Enrollment::where('course_id', $course->id)->count();
        $completedCount = Enrollment::where('course_id', $course->id)
            ->where('completed', true)
            ->count();

        return response()->json([
            'enrolled' => $enrolledCount,
            'completed' => $completedCount,
            'inProgress' => $enrolledCount - $completedCount,
        ]);
    }
}

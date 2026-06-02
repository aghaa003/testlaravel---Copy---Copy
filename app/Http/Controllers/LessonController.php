<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonComment;
use App\Models\LessonLike;
use App\Models\LessonProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{
    /**
     * GET/POST /api/lessons/{lesson}/progress
     */
    public function getProgress(Request $request, Lesson $lesson)
    {
        $user = Auth::user();

        $progress = LessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        return response()->json([
            'progress' => $progress ?? [
                'completed' => false,
                'watched_seconds' => 0,
                'completed_at' => null,
            ],
        ]);
    }

    /**
     * POST /api/lessons/{lesson}/progress
     * Update lesson progress (watch time, completion)
     */
    public function updateProgress(Request $request, Lesson $lesson)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'watched_seconds' => 'nullable|integer|min:0',
            'completed' => 'nullable|boolean',
        ]);

        $progress = LessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            [
                'watched_seconds' => $validated['watched_seconds'] ?? 0,
                'completed' => $validated['completed'] ?? false,
                'completed_at' => ($validated['completed'] ?? false) ? now() : null,
            ]
        );

        return response()->json([
            'message' => 'تم تحديث التقدم',
            'progress' => $progress,
        ], 201);
    }

    /**
     * GET /api/lessons/{lesson}/comments
     */
    public function getComments(Request $request, Lesson $lesson)
    {
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $comments = $lesson->comments()
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $total = $lesson->comments()->whereNull('parent_id')->count();

        return response()->json([
            'comments' => $comments,
            'total' => $total,
        ]);
    }

    /**
     * POST /api/lessons/{lesson}/comments
     * Add comment to lesson
     */
    public function storeComment(Request $request, Lesson $lesson)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:lesson_comments,id',
        ]);

        $comment = $lesson->comments()->create([
            'user_id' => $user->id,
            'content' => $validated['content'],
            'parent_id' => $validated['parent_id'] ?? null,
            'course_id' => $lesson->course_id,
        ]);

        return response()->json([
            'message' => 'تم إضافة التعليق',
            'comment' => $comment->load('user'),
        ], 201);
    }

    /**
     * DELETE /api/lessons/{lesson}/comments/{comment}
     * Delete comment
     */
    public function deleteComment(Request $request, Lesson $lesson, LessonComment $comment)
    {
        $user = Auth::user();

        // Only author or admin can delete
        if ($comment->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'تم حذف التعليق']);
    }

    /**
     * GET/POST /api/lessons/{lesson}/like
     */
    public function getLike(Request $request, Lesson $lesson)
    {
        $user = Auth::user();

        $liked = LessonLike::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->exists();

        $likesCount = $lesson->likes()->count();

        return response()->json([
            'liked' => $liked,
            'likesCount' => $likesCount,
        ]);
    }

    /**
     * POST /api/lessons/{lesson}/like
     * Toggle lesson like
     */
    public function toggleLike(Request $request, Lesson $lesson)
    {
        $user = Auth::user();

        $existing = LessonLike::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            LessonLike::create([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'course_id' => $lesson->course_id,
            ]);
            $liked = true;
        }

        return response()->json([
            'liked' => $liked,
            'likesCount' => $lesson->likes()->count(),
        ]);
    }

    /**
     * GET /api/courses/{course}/progress
     * Get all lessons progress for course
     */
    public function getCourseProgress(Request $request, Course $course)
    {
        $user = Auth::user();

        $lessonsProgress = $user->lessonProgress()
            ->whereHas('lesson', fn($q) => $q->where('course_id', $course->id))
            ->with(['lesson' => fn($q) => $q->orderBy('order_num')])
            ->get();

        return response()->json([
            'lessonsProgress' => $lessonsProgress,
            'totalLessons' => $course->lessons()->count(),
            'completedLessons' => $lessonsProgress->where('completed', true)->count(),
        ]);
    }

    /**
     * GET /api/courses/{course}/viewers
     * Get course viewers/enrollment stats
     */
    public function getCourseViewers(Request $request, Course $course)
    {
        $enrolledCount = DB::table('enrollments')
            ->where('course_id', $course->id)
            ->count();

        $completedCount = DB::table('enrollments')
            ->where('course_id', $course->id)
            ->where('completed', true)
            ->count();

        return response()->json([
            'totalViewers' => $enrolledCount,
            'completed' => $completedCount,
            'inProgress' => $enrolledCount - $completedCount,
        ]);
    }
}

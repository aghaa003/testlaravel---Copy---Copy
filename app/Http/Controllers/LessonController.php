<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonComment;
use App\Models\LessonLike;
use App\Models\LessonProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{
    public function getProgress(Request $request, Lesson $lesson)
    {
        $user = Auth::user();
        $progress = LessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        return response()->json($progress ?? ['lesson_id' => $lesson->id, 'completed' => false, 'watched_seconds' => 0]);
    }

    public function updateProgress(Request $request, Lesson $lesson)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'watched_seconds' => 'nullable|integer|min:0',
            'watchedSeconds' => 'nullable|integer|min:0',
            'completed' => 'nullable|boolean',
            'courseId' => 'nullable|integer',
            'course_id' => 'nullable|integer',
        ]);

        $watchedSeconds = $validated['watched_seconds'] ?? $validated['watchedSeconds'] ?? 0;
        $completed = $validated['completed'] ?? false;

        $progress = LessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            [
                'watched_seconds' => $watchedSeconds,
                'completed' => $completed,
                'completed_at' => $completed ? now() : null,
            ]
        );

        $courseId = $validated['courseId'] ?? $validated['course_id'] ?? $lesson->course_id;
        if ($courseId && $completed) {
            $totalLessons = Lesson::where('course_id', $courseId)->count();
            $completedLessons = LessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', Lesson::where('course_id', $courseId)->pluck('id'))
                ->where('completed', true)
                ->count();

            $pct = $totalLessons > 0 ? (int) (($completedLessons / $totalLessons) * 100) : 0;

            DB::table('enrollments')
                ->where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->update(['progress' => $pct, 'completed' => $pct >= 100]);
        }

        return response()->json(['message' => 'تم تحديث التقدم', 'progress' => $progress]);
    }

    public function getComments(Request $request, Lesson $lesson)
    {
        $comments = $lesson->comments()
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($c) => $this->formatComment($c));

        return response()->json($comments);
    }

    public function storeComment(Request $request, Lesson $lesson)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:lesson_comments,id',
            'parentId' => 'nullable|exists:lesson_comments,id',
        ]);

        $parentId = $validated['parent_id'] ?? $validated['parentId'] ?? null;

        $comment = LessonComment::create([
            'lesson_id' => $lesson->id,
            'course_id' => $lesson->course_id,
            'user_id' => $user->id,
            'content' => $validated['content'],
            'parent_id' => $parentId,
        ]);

        $comment->load('user');

        return response()->json($this->formatComment($comment), 201);
    }

    public function deleteComment(Request $request, Lesson $lesson, LessonComment $comment)
    {
        $user = Auth::user();

        // Allowed to delete: the comment author, the course creator (moderating
        // their own course), employers, or admins.
        $isAuthor = $comment->user_id === $user->id;
        $isCourseOwner = $lesson->course?->creator_id === $user->id;
        $isStaff = in_array($user->role, ['employer', 'admin'], true);

        if (! $isAuthor && ! $isCourseOwner && ! $isStaff) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'تم حذف التعليق']);
    }

    public function getLikes(Request $request, Lesson $lesson)
    {
        return response()->json($this->lessonLikePayload($lesson, Auth::user()));
    }

    public function getLike(Request $request, Lesson $lesson)
    {
        return response()->json($this->lessonLikePayload($lesson, Auth::user()));
    }

    public function toggleLike(Request $request, Lesson $lesson)
    {
        $user = Auth::user();
        $existing = LessonLike::where('user_id', $user->id)->where('lesson_id', $lesson->id)->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            LessonLike::create(['lesson_id' => $lesson->id, 'course_id' => $lesson->course_id, 'user_id' => $user->id]);
            $liked = true;
        }

        return response()->json($this->lessonLikePayload($lesson->fresh(), $user, $liked));
    }

    public function getCourseProgress(Request $request, $courseId)
    {
        $user = Auth::user();
        $progress = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', Lesson::where('course_id', $courseId)->pluck('id'))
            ->get()
            ->map(fn ($lp) => [
                'lessonId' => $lp->lesson_id,
                'completed' => (bool) $lp->completed,
                'watchedSeconds' => $lp->watched_seconds ?? 0,
            ]);

        return response()->json($progress);
    }

    private function lessonLikePayload(Lesson $lesson, $user, ?bool $liked = null): array
    {
        $likeCount = LessonLike::where('lesson_id', $lesson->id)->count();
        if ($liked === null) {
            $liked = $user
                ? LessonLike::where('user_id', $user->id)->where('lesson_id', $lesson->id)->exists()
                : false;
        }

        return ['liked' => $liked, 'likesCount' => $likeCount];
    }

    private function formatComment(LessonComment $comment): array
    {
        $data = $comment->toArray();
        $data['userName'] = $comment->user->name ?? 'مجهول';
        $data['userAvatar'] = $comment->user->avatar_url ?? null;

        if ($comment->relationLoaded('replies')) {
            $data['replies'] = $comment->replies->map(fn ($r) => $this->formatComment($r))->toArray();
        }

        return $data;
    }
}

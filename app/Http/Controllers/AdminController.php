<?php

namespace App\Http\Controllers;

use App\Models\CommunityComment;
use App\Models\LessonComment;
use App\Models\LessonLike;
use App\Models\Review;
use App\Models\User;
use App\Support\LoginLogRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            $employerAllowed = ['getReviews', 'approveReview', 'rejectReview'];
            $action = $request->route()?->getActionMethod();

            if (in_array($action, $employerAllowed, true)) {
                if (! in_array($user?->role, ['admin', 'employer'], true)) {
                    return response()->json(['error' => 'غير مصرح'], 403);
                }
            } elseif ($user?->role !== 'admin') {
                return response()->json(['error' => 'غير مصرح'], 403);
            }

            return $next($request);
        });
    }

    /**
     * GET /api/admin/logs
     */
    public function getLogs(Request $request)
    {
        $limit = min((int) $request->input('limit', 100), 500);
        $offset = (int) $request->input('offset', 0);

        $query = DB::table('login_logs')->orderByDesc('created_at');
        $total = $query->count();
        $logs = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'logs' => $logs->map(fn ($log) => LoginLogRecorder::format($log)),
            'total' => $total,
        ]);
    }

    /**
     * GET /api/admin/engagements
     * SPA expects { engagements: [...] } with enriched like/comment items.
     */
    public function getEngagements(Request $request)
    {
        $limit = min((int) $request->input('limit', 50), 200);

        $lessonComments = LessonComment::with(['user', 'lesson.course'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LessonComment $c) => [
                'id' => (string) $c->id,
                'userName' => $c->user?->name ?? 'User',
                'lessonTitle' => $c->lesson?->title ?? 'درس',
                'courseTitle' => $c->lesson?->course?->title ?? 'كورس',
                'type' => 'comment',
                'content' => $c->content,
                'createdAt' => $c->created_at,
            ]);

        $likes = LessonLike::with(['user', 'lesson.course'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LessonLike $l) => [
                'id' => (string) $l->id,
                'userName' => $l->user?->name ?? 'User',
                'lessonTitle' => $l->lesson?->title ?? 'درس',
                'courseTitle' => $l->lesson?->course?->title ?? 'كورس',
                'type' => 'like',
                'createdAt' => $l->created_at,
            ]);

        $engagements = $lessonComments
            ->concat($likes)
            ->sortByDesc('createdAt')
            ->values()
            ->take($limit);

        return response()->json([
            'engagements' => $engagements,
            'items' => $engagements,
            'total' => $engagements->count(),
        ]);
    }

    /**
     * GET /api/admin/comments
     */
    public function getComments(Request $request)
    {
        $limit = min((int) $request->input('limit', 50), 200);
        $offset = (int) $request->input('offset', 0);
        $type = $request->input('type');

        if ($type === 'lesson') {
            $query = LessonComment::with(['user', 'lesson.course']);
            $total = $query->count();
            $comments = $query->orderByDesc('created_at')->skip($offset)->take($limit)->get();

            return response()->json([
                'comments' => $comments->map(fn ($c) => $this->formatLessonComment($c)),
                'total' => $total,
            ]);
        }

        if ($type === 'community') {
            $query = CommunityComment::with(['user', 'post']);
            $total = $query->count();
            $comments = $query->orderByDesc('created_at')->skip($offset)->take($limit)->get();

            return response()->json([
                'comments' => $comments->map(fn ($c) => $this->formatCommunityComment($c)),
                'total' => $total,
            ]);
        }

        $lessonItems = LessonComment::with(['user', 'lesson.course'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => $this->formatLessonComment($c));

        $communityItems = CommunityComment::with(['user', 'post'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => $this->formatCommunityComment($c));

        $merged = $lessonItems->concat($communityItems)
            ->sortByDesc(fn ($c) => $c['createdAt'])
            ->values()
            ->slice($offset, $limit)
            ->values();

        $total = LessonComment::count() + CommunityComment::count();

        return response()->json([
            'comments' => $merged,
            'total' => $total,
        ]);
    }

    /**
     * DELETE /api/admin/comments/{id}
     * Frontend sends a single id (lesson or community comment).
     */
    public function deleteCommentById($id)
    {
        $lesson = LessonComment::find($id);
        if ($lesson) {
            $lesson->delete();

            return response()->json(['success' => true, 'message' => 'تم حذف التعليق']);
        }

        $community = CommunityComment::find($id);
        if ($community) {
            $community->delete();

            return response()->json(['success' => true, 'message' => 'تم حذف التعليق']);
        }

        return response()->json(['error' => 'Comment not found'], 404);
    }

    /**
     * DELETE /api/admin/comments/{type}/{id}
     */
    public function deleteComment($type, $id)
    {
        if ($type === 'lesson') {
            LessonComment::findOrFail($id)->delete();
        } elseif ($type === 'community') {
            CommunityComment::findOrFail($id)->delete();
        } else {
            return response()->json(['error' => 'Invalid comment type'], 400);
        }

        return response()->json(['success' => true, 'message' => 'تم حذف التعليق']);
    }

    /**
     * GET /api/admin/reviews
     */
    public function getReviews(Request $request)
    {
        $limit = min((int) $request->input('limit', 100), 500);
        $offset = (int) $request->input('offset', 0);
        $status = $request->input('status');

        $query = Review::with(['user', 'course']);

        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $reviews = $query->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn (Review $review) => $this->formatReview($review));

        return response()->json([
            'reviews' => $reviews,
            'total' => $total,
        ]);
    }

    public function approveReview(Request $request, Review $review)
    {
        $review->update(['status' => 'approved']);

        return response()->json([
            'success' => true,
            'message' => 'تم الموافقة على التقييم',
            'review' => $this->formatReview($review->fresh(['user', 'course'])),
        ]);
    }

    public function rejectReview(Request $request, Review $review)
    {
        $reason = $request->input('reason');

        $review->update([
            'status' => 'rejected',
            'rejection_reason' => is_string($reason) && $reason !== '' ? $reason : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم رفض التقييم',
            'review' => $this->formatReview($review->fresh(['user', 'course'])),
        ]);
    }

    public function banUser(Request $request, User $user)
    {
        $user->update(['banned' => true]);

        return response()->json([
            'success' => true,
            'banned' => true,
            'message' => 'تم حظر المستخدم',
            'user' => $user,
        ]);
    }

    public function unbanUser(Request $request, User $user)
    {
        $user->update(['banned' => false]);

        return response()->json([
            'success' => true,
            'banned' => false,
            'message' => 'تم إلغاء حظر المستخدم',
            'user' => $user,
        ]);
    }

    private function formatLessonComment(LessonComment $c): array
    {
        return [
            'id' => (string) $c->id,
            'userName' => $c->user?->name ?? 'User',
            'lessonTitle' => $c->lesson?->title ?? 'درس',
            'courseTitle' => $c->lesson?->course?->title ?? 'كورس',
            'content' => $c->content,
            'status' => 'published',
            'createdAt' => $c->created_at,
        ];
    }

    private function formatCommunityComment(CommunityComment $c): array
    {
        return [
            'id' => (string) $c->id,
            'userName' => $c->user?->name ?? 'User',
            'lessonTitle' => $c->post?->title ?? 'مشاركة',
            'courseTitle' => 'مجتمع',
            'content' => $c->content,
            'status' => 'published',
            'createdAt' => $c->created_at,
        ];
    }

    private function formatReview(Review $review): array
    {
        $isHome = $review->course_id === null;

        return [
            'id' => (string) $review->id,
            'userName' => $isHome
                ? ($review->reviewer_name ?? $review->user?->name ?? 'زائر')
                : ($review->user?->name ?? 'مستخدم'),
            'userAvatar' => $review->user?->avatar_url,
            'courseTitle' => $isHome ? 'آراء الصفحة الرئيسية' : ($review->course?->title ?? 'كورس'),
            'rating' => $review->rating,
            'comment' => $review->comment ?? '',
            'reviewerName' => $review->reviewer_name ?? '',
            'isHomeReview' => $isHome,
            'status' => $review->status,
            'createdAt' => $review->created_at,
        ];
    }
}

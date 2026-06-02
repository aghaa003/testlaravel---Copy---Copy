<?php

namespace App\Http\Controllers;

use App\Models\CommunityComment;
use App\Models\LessonComment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Middleware check for admin
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Auth::user()?->role !== 'admin') {
                return response()->json(['message' => 'غير مصرح'], 403);
            }
            return $next($request);
        });
    }

    /**
     * GET /api/admin/logs
     * Get login logs
     */
    public function getLogs(Request $request)
    {
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $logs = DB::table('login_logs')
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $total = DB::table('login_logs')->count();

        return response()->json([
            'logs' => $logs,
            'total' => $total,
        ]);
    }

    /**
     * GET /api/admin/engagements
     * Get platform engagement metrics
     */
    public function getEngagements(Request $request)
    {
        $totalSubmissions = DB::table('challenge_submissions')->count();
        $successfulSubmissions = DB::table('challenge_submissions')
            ->where('success', true)
            ->count();

        $lessonComments = DB::table('lesson_comments')->count();
        $lessonLikes = DB::table('lesson_likes')->count();

        $communityPosts = DB::table('community_posts')->count();
        $communityComments = DB::table('community_comments')->count();
        $communityLikes = DB::table('community_post_likes')->count();

        $enrollments = DB::table('enrollments')->count();
        $completedCourses = DB::table('enrollments')
            ->where('completed', true)
            ->count();

        return response()->json([
            'challenges' => [
                'totalSubmissions' => $totalSubmissions,
                'successfulSubmissions' => $successfulSubmissions,
                'successRate' => $totalSubmissions > 0
                    ? ($successfulSubmissions / $totalSubmissions) * 100
                    : 0,
            ],
            'lessons' => [
                'comments' => $lessonComments,
                'likes' => $lessonLikes,
            ],
            'community' => [
                'posts' => $communityPosts,
                'comments' => $communityComments,
                'likes' => $communityLikes,
            ],
            'courses' => [
                'enrollments' => $enrollments,
                'completedCourses' => $completedCourses,
            ],
        ]);
    }

    /**
     * GET /api/admin/comments
     * Get all comments (lesson + community)
     * ✅ Admin has full access via /admin/comments (routed to ModerationController)
     */
    public function getComments(Request $request)
    {
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);
        $type = $request->input('type'); // 'lesson' or 'community'

        if ($type === 'lesson') {
            $total = DB::table('lesson_comments')->count();
            $comments = DB::table('lesson_comments')
                ->join('users', 'lesson_comments.user_id', '=', 'users.id')
                ->select('lesson_comments.*', 'users.name as user_name')
                ->orderBy('lesson_comments.created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();
        } elseif ($type === 'community') {
            $total = DB::table('community_comments')->count();
            $comments = DB::table('community_comments')
                ->join('users', 'community_comments.user_id', '=', 'users.id')
                ->select('community_comments.*', 'users.name as user_name')
                ->orderBy('community_comments.created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();
        } else {
            // Return both types
            $lessonComments = DB::table('lesson_comments')
                ->select(DB::raw("'lesson' as type"), 'lesson_comments.*')
                ->limit(10);

            $communityComments = DB::table('community_comments')
                ->select(DB::raw("'community' as type"), 'community_comments.*')
                ->limit(10);

            $comments = $lessonComments->unionAll($communityComments)
                ->orderBy('created_at', 'desc')
                ->get();

            $total = DB::table('lesson_comments')->count() +
                     DB::table('community_comments')->count();
        }

        return response()->json([
            'comments' => $comments,
            'total' => $total,
        ]);
    }

    /**
     * DELETE /api/admin/comments/{type}/{id}
     * Delete a comment (lesson or community)
     * ✅ Admin has full access via /admin/comments/{type}/{id}
     */
    public function deleteComment(Request $request, $type, $id)
    {
        if ($type === 'lesson') {
            $comment = LessonComment::findOrFail($id);
        } elseif ($type === 'community') {
            $comment = CommunityComment::findOrFail($id);
        } else {
            return response()->json(['message' => 'Invalid comment type'], 400);
        }

        $comment->delete();

        return response()->json(['message' => 'تم حذف التعليق']);
    }

    /**
     * GET /api/admin/reviews
     * Get all reviews for moderation (pending, approved, rejected)
     * ✅ Admin has full access via /admin/reviews
     */
    public function getReviews(Request $request)
    {
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);
        $status = $request->input('status'); // pending, approved, rejected

        $query = Review::with('user');

        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $reviews = $query->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'reviews' => $reviews,
            'total' => $total,
        ]);
    }

    /**
     * POST /api/admin/reviews/{review}/approve
     * Approve a review
     * ✅ Admin has full access via /admin/reviews/{review}/approve
     */
    public function approveReview(Request $request, Review $review)
    {
        $review->update(['status' => 'approved']);

        return response()->json([
            'message' => 'تم الموافقة على التقييم',
            'review' => $review,
        ]);
    }

    /**
     * POST /api/admin/reviews/{review}/reject
     * Reject a review
     * ✅ Admin has full access via /admin/reviews/{review}/reject
     */
    public function rejectReview(Request $request, Review $review)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $review->update([
            'status' => 'rejected',
            'message' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'تم رفض التقييم',
            'review' => $review,
        ]);
    }

    /**
     * POST /api/admin/users/{user}/ban
     * Ban a user
     */
    public function banUser(Request $request, User $user)
    {
        $user->update(['banned' => true]);

        return response()->json([
            'message' => 'تم حظر المستخدم',
            'user' => $user,
        ]);
    }

    /**
     * POST /api/admin/users/{user}/unban
     * Unban a user
     */
    public function unbanUser(Request $request, User $user)
    {
        $user->update(['banned' => false]);

        return response()->json([
            'message' => 'تم إلغاء حظر المستخدم',
            'user' => $user,
        ]);
    }
}

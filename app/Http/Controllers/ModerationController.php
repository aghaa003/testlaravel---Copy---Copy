<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Assignment;
use App\Models\Review;
use App\Models\LessonComment;
use App\Models\CommunityComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModerationController extends Controller
{
    // Role enforcement (employer/admin) lives in routes/api.php via `role:` middleware.

    /**
     * GET /api/employer/courses
     * Get all courses for moderation (employer and admin)
     */
    public function getCourses(Request $request)
    {
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $query = Course::with('creator');
        $total = $query->count();
        $courses = $query->skip($offset)->take($limit)->orderBy('created_at', 'desc')->get();

        return response()->json([
            'courses' => $courses,
            'total' => $total,
        ]);
    }

    /**
     * GET /api/employer/assignments
     * Get all assignments for moderation (employer and admin)
     */
    public function getAssignments(Request $request)
    {
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        $query = Assignment::with('creator');
        $total = $query->count();
        $assignments = $query->skip($offset)->take($limit)->orderBy('created_at', 'desc')->get();

        return response()->json([
            'assignments' => $assignments,
            'total' => $total,
        ]);
    }

    /**
     * GET /api/employer/comments
     * Get all comments (lesson and community) for moderation
     */
    public function getComments(Request $request)
    {
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);
        $type = $request->input('type'); // 'lesson' or 'community'

        if ($type === 'lesson') {
            $query = LessonComment::with(['user', 'lesson']);
        } elseif ($type === 'community') {
            $query = CommunityComment::with(['user', 'post']);
        } else {
            // Merge both comment types without loading every row in either table:
            // each side only needs to supply enough of its most-recent rows to cover
            // this page window, since the final merged-and-sorted slice can never
            // need more than offset+limit rows from either table.
            $window = $offset + $limit;
            $lessonComments = LessonComment::with(['user', 'lesson'])->orderBy('created_at', 'desc')->take($window)->get();
            $communityComments = CommunityComment::with(['user', 'post'])->orderBy('created_at', 'desc')->take($window)->get();

            $merged = collect($lessonComments)->merge($communityComments)
                ->sortByDesc('created_at')
                ->values()
                ->slice($offset, $limit);

            return response()->json([
                'comments' => $merged,
                'total' => LessonComment::count() + CommunityComment::count(),
            ]);
        }

        $total = $query->count();
        $comments = $query->skip($offset)->take($limit)->orderBy('created_at', 'desc')->get();

        return response()->json([
            'comments' => $comments,
            'total' => $total,
        ]);
    }

    /**
     * DELETE /api/employer/comments/{type}/{id}
     * Delete a lesson or community comment
     */
    public function deleteComment(Request $request, $type, $id)
    {
        if ($type === 'lesson') {
            $comment = LessonComment::findOrFail($id);
        } elseif ($type === 'community') {
            $comment = CommunityComment::findOrFail($id);
        } else {
            return response()->json(['error' => 'Invalid comment type'], 400);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }

    /**
     * GET /api/employer/reviews
     * Get all reviews for moderation (pending, approved, rejected)
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
     * POST /api/employer/reviews/{review}/approve
     * Approve a review (course or home)
     */
    public function approveReview(Request $request, Review $review)
    {
        if ($review->status === 'approved') {
            return response()->json(['message' => 'This review is already approved'], 200);
        }

        $review->update(['status' => 'approved']);

        return response()->json([
            'message' => 'Review approved successfully',
            'review' => $review,
        ]);
    }

    /**
     * POST /api/employer/reviews/{review}/reject
     * Reject a review (course or home)
     */
    public function rejectReview(Request $request, Review $review)
    {
        $reason = $request->input('reason');

        $review->update([
            'status' => 'rejected',
            'rejection_reason' => is_string($reason) && $reason !== '' ? $reason : null,
        ]);

        return response()->json([
            'message' => 'Review rejected',
            'review' => $review,
        ]);
    }
}

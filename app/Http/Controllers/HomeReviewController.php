<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeReviewController extends Controller
{
    /**
     * GET /api/home-reviews
     * Get approved reviews for home page (no course_id)
     */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 5);

        $reviews = Review::whereNull('course_id')
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    // Show whatever name the reviewer typed; if they left it blank,
                    // show "anonymous" rather than leaking their real account name.
                    'reviewerName' => trim((string) $review->reviewer_name) !== '' ? $review->reviewer_name : 'مستخدم مجهول',
                    'createdAt' => $review->created_at,
                ];
            });

        return response()->json([
            'reviews' => $reviews,
            'total' => $reviews->count(),
        ]);
    }

    /**
     * POST /api/home-reviews
     * Submit a review for homepage (status=pending initially)
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:5000',
            'reviewer_name' => 'nullable|string|max:255',
        ]);

        // ✅ Fixed: never default to the real account name. A blank name means
        // the reviewer wants to stay anonymous — index() displays that as
        // "مستخدم مجهول" rather than leaking who actually wrote the review.
        $reviewerName = trim((string) ($validated['reviewer_name'] ?? ''));

        $review = Review::create([
            'user_id' => $user->id,
            'course_id' => null, // Homepage review, not for specific course
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'reviewer_name' => $reviewerName !== '' ? $reviewerName : null,
            'status' => 'pending', // Will be approved by admin
        ]);

        return response()->json([
            'message' => 'شكراً لتقييمك! سيتم نشره بعد الموافقة',
            'review' => $review,
        ], 201);
    }

    /**
     * POST /api/home-reviews/{review}/approve
     * Approve a review (employer or admin only)
     */
    public function approve(Request $request, Review $review)
    {
        // Role (employer/admin) enforced by `role:` middleware in routes/api.php.

        if ($review->status === 'approved') {
            return response()->json(['message' => 'This review is already approved'], 200);
        }

        $review->update(['status' => 'approved']);

        return response()->json(['message' => 'Review approved successfully', 'review' => $review]);
    }

    /**
     * POST /api/home-reviews/{review}/reject
     * Reject a review (employer or admin only)
     */
    public function reject(Request $request, Review $review)
    {
        // Role (employer/admin) enforced by `role:` middleware in routes/api.php.

        $reason = $request->input('reason');

        $review->update([
            'status' => 'rejected',
            'rejection_reason' => is_string($reason) && $reason !== '' ? $reason : null,
        ]);

        return response()->json(['message' => 'Review rejected', 'review' => $review]);
    }
}

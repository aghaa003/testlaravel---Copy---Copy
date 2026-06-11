<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\User;
use App\Services\CodeReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChallengeController extends Controller
{
    // 1. جلب قائمة التحديات (تطابق ChallengeListResponse)
    public function index(Request $request)
    {
        $query = Challenge::query();

        // Hide disabled challenges from the public. Creators may see their own
        // disabled ones; employers/admins see all when include_inactive=1.
        $this->applyActiveScope($query, $request, 'creator_id');

        // التصفية حسب الصعوبة (easy, medium, hard)
        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        // التصفية حسب الفئة (category)
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $total = $query->count();
        $challenges = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'challenges' => $challenges,
            'total' => $total,
        ]);
    }

    // 2. إنشاء تحدي جديد - يتطلب دور: creator أو employer أو admin
    public function store(Request $request)
    {
        $user = Auth::user();

        // Role (creator/employer/admin) enforced by `role:` middleware in routes/api.php.

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'difficulty' => 'required|in:easy,medium,hard',
            'category' => 'required|string',
            'points' => 'required|integer|min:0',
        ]);

        $challenge = Challenge::create([
            ...$validated,
            'creator_id' => $user->id,
        ]);

        return response()->json($challenge, 201);
    }

    // 3. عرض تفاصيل التحدي
    public function show(Challenge $challenge)
    {
        return response()->json($challenge);
    }

    public function submit(Request $request, Challenge $challenge, CodeReviewService $reviewer)
    {
        // ✅ Fix 11: use Auth::id(), ignore user_id from request
        $userId = Auth::id();

        if (! $userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'solution' => 'nullable|string|max:100000',
            'code' => 'nullable|string|max:100000',
            'language' => 'nullable|string|max:100',
            'image' => 'nullable|string|max:20000000', // base64 image of the answer (optional)
        ]);

        $solution = $validated['solution'] ?? $validated['code'] ?? '';
        if (! empty($validated['image'])) {
            $fromImage = $reviewer->extractCodeFromImage($validated['image']);
            if ($fromImage) {
                $solution = trim($solution) !== '' ? $solution."\n".$fromImage : $fromImage;
            }
        }
        if (trim($solution) === '') {
            return response()->json([
                'success' => false,
                'pointsEarned' => 0,
                'score' => 0,
                'message' => 'يرجى كتابة كود برمجي للحل.',
            ], 422);
        }

        $language = $validated['language'] ?? $challenge->category ?? 'General';
        $review = $reviewer->review($solution, $language, $challenge->description, 'challenge');
        $isSuccess = ($review['verdict'] ?? 'no') === 'yes' && (int) ($review['score'] ?? 0) >= 60;

        $submission = DB::transaction(function () use ($challenge, $userId, $solution, $language, $review, $isSuccess) {
            // Award points only the FIRST time a user solves a challenge — prevents
            // farming points by resubmitting an already-solved challenge.
            $alreadySolved = ChallengeSubmission::where('user_id', $userId)
                ->where('challenge_id', $challenge->id)
                ->where('success', true)
                ->lockForUpdate()
                ->exists();
            $pointsEarned = ($isSuccess && ! $alreadySolved) ? $challenge->points : 0;

            $sub = ChallengeSubmission::create([
                'user_id' => $userId,
                'challenge_id' => $challenge->id,
                'solution' => $solution,
                'language' => $language,
                'success' => $isSuccess,
                'points_earned' => $pointsEarned,
                'score' => (int) ($review['score'] ?? 0),
                'message' => $isSuccess
                    ? 'لقد اجتزت التحدي بنجاح!'
                    : ($review['hint'] ?? $review['summary'] ?? 'هناك خطأ في الكود البرمجي.'),
            ]);

            $challenge->increment('total_submissions');
            $successCount = $challenge->submissions()->where('success', true)->count();
            $challenge->update(['success_rate' => ($successCount / max($challenge->total_submissions, 1)) * 100]);

            if ($pointsEarned > 0) {
                User::where('id', $userId)->increment('points', $pointsEarned);
            }

            return $sub;
        });

        return response()->json([
            'success' => $submission->success,
            'pointsEarned' => $submission->points_earned,
            'score' => $submission->score,
            'message' => $submission->message,
            'review' => $review,
        ]);
    }

    // --- معالجة المحتوى (Content Moderation) --- //

    // 5. حذف تحدي (فقط منشئه أو admin) - يتطلب إضافة creator_id في جدول التحديات
    public function deleteChallenge(Request $request, Challenge $challenge)
    {
        // Owner-or-admin enforced by ChallengePolicy.
        $this->authorize('delete', $challenge);

        $challenge->delete();

        return response()->json(['message' => 'Challenge deleted successfully']);
    }

    // 6. تحديث تحدي (فقط منشئه أو admin)
    public function updateChallenge(Request $request, Challenge $challenge)
    {
        // Owner-or-admin enforced by ChallengePolicy.
        $this->authorize('update', $challenge);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'category' => 'nullable|string',
            'points' => 'nullable|integer|min:0',
        ]);

        $challenge->update($validated);

        return response()->json($challenge);
    }

    // Disable/enable a challenge (creator-owned, or employer/admin)
    public function toggleActive(Request $request, Challenge $challenge)
    {
        $this->authorize('toggleActive', $challenge);
        $challenge->update(['is_active' => ! $challenge->is_active]);

        return response()->json(['success' => true, 'is_active' => $challenge->is_active]);
    }

    // GET /api/challenges/my-submissions
    public function mySubmissions(Request $request)
    {
        $submissions = ChallengeSubmission::where('user_id', Auth::id())
            ->with('challenge')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($submissions);
    }
}

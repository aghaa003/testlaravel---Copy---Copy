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

        // ✅ التحقق من الصلاحيات - creator يمكنه إنشاء تحديات (لأنها محتوى يمكن معالجته)
        if (! in_array($user->role, ['creator', 'employer', 'admin'])) {
            return response()->json([
                'error' => 'Only creators, employers, and admins can create challenges',
                'your_role' => $user->role,
            ], 403);
        }

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
        ]);

        $solution = $validated['solution'] ?? $validated['code'] ?? '';
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
        $pointsEarned = $isSuccess ? $challenge->points : 0;

        $submission = DB::transaction(function () use ($challenge, $userId, $solution, $language, $review, $isSuccess, $pointsEarned) {
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
            $challenge->update(['success_rate' => ($successCount / $challenge->total_submissions) * 100]);

            if ($isSuccess) {
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
        $user = Auth::user();

        if ($user->role !== 'admin' && $user->id !== $challenge->creator_id) {
            return response()->json(['error' => 'You can only delete your own challenges'], 403);
        }

        $challenge->delete();

        return response()->json(['message' => 'Challenge deleted successfully']);
    }

    // 6. تحديث تحدي (فقط منشئه أو admin)
    public function updateChallenge(Request $request, Challenge $challenge)
    {
        $user = Auth::user();

        if ($user->role !== 'admin' && $user->id !== $challenge->creator_id) {
            return response()->json(['error' => 'You can only update your own challenges'], 403);
        }

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
}

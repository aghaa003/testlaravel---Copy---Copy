<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\User;
use Illuminate\Http\Request;
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

    // 2. إنشاء تحدي جديد (من قبل الإدارة)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'difficulty' => 'required|in:easy,medium,hard',
            'category' => 'required|string',
            'points' => 'required|integer|min:0',
        ]);

        $challenge = Challenge::create($validated);

        return response()->json($challenge, 201);
    }

    // 3. عرض تفاصيل التحدي
    public function show(Challenge $challenge)
    {
        return response()->json($challenge);
    }

    // 4. تسليم حل التحدي ومعالجته (Submit Challenge)
    public function submit(Request $request, Challenge $challenge)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'solution' => 'required|string',
            'language' => 'required|string',
        ]);

        // ملاحظة: هنا نقوم بمحاكاة فحص الكود (Mock Grading)
        // في مشروعك الأصلي قد ترغب بربطه بـ AI Reviewer أو مصحح برمي تلقائي
        $isSuccess = true; // سنفترض نجاح الحل تلقائياً للمحاكاة حالياً
        $pointsEarned = $isSuccess ? $challenge->points : 0;

        $submission = DB::transaction(function () use ($challenge, $validated, $isSuccess, $pointsEarned) {
            // 1. تسجيل عملية التسليم
            $sub = ChallengeSubmission::create([
                'user_id' => $validated['user_id'],
                'challenge_id' => $challenge->id,
                'solution' => $validated['solution'],
                'language' => $validated['language'],
                'success' => $isSuccess,
                'points_earned' => $pointsEarned,
                'message' => $isSuccess ? 'لقد اجتزت التحدي بنجاح!' : 'هناك خطأ في الكود البرمجي.',
            ]);

            // 2. تحديث إحصائيات التحدي
            $challenge->increment('total_submissions');

            $successfulSubmissions = $challenge->submissions()->where('success', true)->count();
            $newSuccessRate = ($successfulSubmissions / $challenge->total_submissions) * 100;
            $challenge->update(['success_rate' => $newSuccessRate]);

            // 3. إضافة النقاط للمستخدم في حال النجاح
            if ($isSuccess) {
                User::where('id', $validated['user_id'])->increment('points', $pointsEarned);
            }

            return $sub;
        });

        return response()->json([
            'success' => $submission->success,
            'pointsEarned' => $submission->points_earned,
            'message' => $submission->message,
        ]);
    }
}

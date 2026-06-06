<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    // 1. جلب قائمة الدورات (تطابق CourseListResponse)
    public function index(Request $request)
    {
        $query = Course::query()->with('creator');

        // تصفية حسب الفئة (category) إذا تم تمريرها
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $total = $query->count();
        $courses = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'courses' => $courses,
            'total' => $total,
        ]);
    }

    // store() — fix bug 9: accept creatorId camelCase
    public function store(Request $request)
    {
        $user = Auth::user();

        if (! in_array($user->role, ['creator', 'employer', 'admin'])) {
            return response()->json([
                'error' => 'Only creators, employers, and admins can create courses',
                'your_role' => $user->role,
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail_url' => 'nullable|string',
            'thumbnailUrl' => 'nullable|string',
            'category' => 'required|string',
            'level' => 'required|in:beginner,intermediate,advanced',
            'language' => 'nullable|string',
        ]);

        // ✅ Fix 9: accept both creator_id and creatorId, fallback to auth user
        $creatorId = $request->input('creator_id')
            ?? $request->input('creatorId')
            ?? $user->id;

        $course = Course::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'thumbnail_url' => $validated['thumbnail_url'] ?? $validated['thumbnailUrl'] ?? null,
            'category' => $validated['category'],
            'level' => $validated['level'],
            'language' => $validated['language'] ?? null,
            'creator_id' => $creatorId,
        ]);

        $course->load('creator');

        return response()->json($course, 201);
    }

    // 3. عرض تفاصيل الدورة مع الدروس (تطابق CourseDetail)
    public function show(Course $course)
    {
        // جلب المنشئ والدروس المرتبطة بالدورة وترتيبها
        $course->load(['creator', 'lessons' => function ($query) {
            $query->orderBy('order_num', 'asc');
        }]);

        return response()->json($course);
    }

    // 4. جلب الدورات المميزة (Featured Courses)
    public function featured()
    {
        // سنجلب الدورات الأعلى تقييماً كمثال للدورات المميزة
        $featuredCourses = Course::with('creator')
            ->orderBy('average_rating', 'desc')
            ->take(5)
            ->get();

        return response()->json($featuredCourses);
    }

    // --- الدروس (Lessons) --- //

    // storeLesson() — fix bug 10: accept camelCase fields
    public function storeLesson(Request $request, Course $course)
    {
        $user = Auth::user();

        if (! in_array($user->role, ['creator', 'employer', 'admin'])) {
            return response()->json([
                'error' => 'Only creators, employers, and admins can add lessons',
                'your_role' => $user->role,
            ], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        // ✅ Fix 10: resolve both snake_case and camelCase
        $lesson = $course->lessons()->create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'video_url' => $request->input('video_url') ?? $request->input('videoUrl'),
            'pdf_url' => $request->input('pdf_url') ?? $request->input('pdfUrl'),
            'attachment_url' => $request->input('attachment_url') ?? $request->input('attachmentUrl'),
            'attachment_name' => $request->input('attachment_name') ?? $request->input('attachmentName'),
            'duration' => $request->input('duration'),
            'order_num' => $request->input('order_num') ?? $request->input('order') ?? 1,
        ]);

        $course->increment('total_lessons');

        return response()->json($lesson, 201);
    }

    // --- التقييمات (Reviews) --- //

    // 6. جلب تقييمات الدورة
    public function reviews(Course $course)
    {
        // جلب التقييمات مع بيانات المستخدم الذي كتبها
        $reviews = $course->reviews()->with('user')->latest()->get();

        return response()->json($reviews);
    }

    // ✅ Warn 3: fix storeReview to use Auth::id()
    public function storeReview(Request $request, Course $course)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id();

        $review = $course->reviews()->create($validated);

        $newTotal = $course->total_reviews + 1;
        $newAverage = (($course->average_rating * $course->total_reviews) + $validated['rating']) / $newTotal;

        $course->update([
            'total_reviews' => $newTotal,
            'average_rating' => round($newAverage, 2),
        ]);

        return response()->json($review->load('user'), 201);
    }

    /**
     * DELETE /api/courses/{course}/reviews/{review}
     * Only the course creator may remove a review on their course.
     */
    public function destroyReview(Request $request, Course $course, Review $review)
    {
        $user = Auth::user();

        if ($review->course_id !== $course->id) {
            return response()->json(['error' => 'Review does not belong to this course'], 404);
        }

        if ($user->id !== $course->creator_id) {
            return response()->json([
                'error' => 'Only the course creator can delete reviews on this course',
            ], 403);
        }

        $review->delete();

        $remaining = $course->reviews()->get();
        $count = $remaining->count();
        $average = $count > 0 ? round($remaining->avg('rating'), 2) : 0;

        $course->update([
            'total_reviews' => $count,
            'average_rating' => $average,
        ]);

        return response()->json([
            'success' => true,
            'totalReviews' => $count,
            'averageRating' => $average,
        ]);
    }

    // --- معالجة المحتوى (Content Moderation) --- //

    // 8. حذف دورة (فقط منشئها أو admin)
    public function destroyCourse(Request $request, Course $course)
    {
        $user = Auth::user();

        // ✅ التحقق من الصلاحيات - فقط منشئ الدورة أو admin يمكنهم حذفها
        if ($user->id !== $course->creator_id && $user->role !== 'admin') {
            return response()->json(['error' => 'You can only delete your own courses'], 403);
        }

        $course->delete();

        return response()->json(['message' => 'Course deleted successfully']);
    }

    // 9. تحديث دورة (فقط منشئها أو admin)
    public function updateCourse(Request $request, Course $course)
    {
        $user = Auth::user();

        // ✅ التحقق من الصلاحيات - فقط منشئ الدورة أو admin يمكنهم تعديلها
        if ($user->id !== $course->creator_id && $user->role !== 'admin') {
            return response()->json(['error' => 'You can only update your own courses'], 403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'thumbnail_url' => 'nullable|url',
            'category' => 'nullable|string',
            'level' => 'nullable|in:beginner,intermediate,advanced',
        ]);

        $course->update($validated);

        return response()->json($course);
    }

    // 10. حذف درس (فقط منشئ الدورة أو admin)
    public function deleteLesson(Request $request, Lesson $lesson)
    {
        $user = Auth::user();
        $course = $lesson->course;

        // ✅ التحقق من الصلاحيات
        if ($user->id !== $course->creator_id && $user->role !== 'admin') {
            return response()->json(['error' => 'You can only delete lessons from your own courses'], 403);
        }

        $lesson->delete();
        $course->decrement('total_lessons');

        return response()->json(['message' => 'Lesson deleted successfully']);
    }
}

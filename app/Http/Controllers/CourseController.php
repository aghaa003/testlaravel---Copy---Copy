<?php

namespace App\Http\Controllers;

use App\Models\Course;
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

    // 2. إنشاء دورة جديدة (CreateCourseBody)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail_url' => 'nullable|url',
            'category' => 'required|string',
            'level' => 'required|in:beginner,intermediate,advanced',
            'language' => 'nullable|string',
            'creator_id' => 'required|exists:users,id', // في المستقبل، سيتم أخذ الـ ID من Auth
        ]);

        $course = Course::create($validated);
        // جلب الدورة مع بيانات المنشئ لإرجاعها كاملة
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

    // 5. إضافة درس جديد لدورة معينة (CreateLessonBody)
    public function storeLesson(Request $request, Course $course)
{
    $validated = $request->validate([
        'title'           => 'required|string|max:255',
        'description'     => 'nullable|string',
        'video_url'       => 'nullable|string',
        'pdf_url'         => 'nullable|string',
        'attachment_url'  => 'nullable|string',
        'attachment_name' => 'nullable|string|max:255',
        'duration'        => 'nullable|integer',
        'order_num'       => 'required|integer',
    ]);

    $lesson = $course->lessons()->create($validated);
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

    // 7. إضافة تقييم جديد لدورة (CreateReviewBody)
    public function storeReview(Request $request, Course $course)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id', // لاحقاً من الـ Auth
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $review = $course->reviews()->create($validated);

        // تحديث التقييم المتوسط وعدد التقييمات في الدورة
        $newTotalReviews = $course->total_reviews + 1;
        $newAverageRating = (($course->average_rating * $course->total_reviews) + $validated['rating']) / $newTotalReviews;

        $course->update([
            'total_reviews' => $newTotalReviews,
            'average_rating' => $newAverageRating,
        ]);

        $review->load('user');

        return response()->json($review, 201);
    }
}

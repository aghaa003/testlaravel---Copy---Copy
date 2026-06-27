<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    // 1. جلب قائمة الدورات (تطابق CourseListResponse)
    public function index(Request $request)
    {
        $query = Course::query()->with('creator');

        // Hide disabled courses from the public (creator sees own; employer/admin all).
        $this->applyActiveScope($query, $request, 'creator_id');

        // تصفية حسب الفئة (category) إذا تم تمريرها
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $total = $query->count();
        $courses = $query->skip($offset)->take($limit)->get()
            ->map(fn (Course $course) => $this->formatCourse($course));

        return response()->json([
            'courses' => $courses,
            'total' => $total,
        ]);
    }

    // يحول الدورة إلى الشكل camelCase المتوافق مع واجهة Course في الواجهة الأمامية
    private function formatCourse(Course $course): array
    {
        return [
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->description,
            'thumbnailUrl' => $course->thumbnail_url,
            'category' => $course->category,
            'level' => $course->level,
            'language' => $course->language,
            'creatorId' => $course->creator_id,
            'creatorName' => $course->creator->name ?? null,
            'creatorAvatar' => $course->creator->avatar_url ?? null,
            'averageRating' => (float) $course->average_rating,
            'totalReviews' => $course->total_reviews,
            'totalLessons' => $course->total_lessons,
            'totalEnrollments' => $course->total_enrollments,
            'isActive' => $course->is_active,
            'createdAt' => $course->created_at?->toJSON(),
        ];
    }

    // store() — fix bug 9: accept creatorId camelCase
    public function store(Request $request)
    {
        $user = Auth::user();

        // Role (creator/employer/admin) enforced by `role:` middleware in routes/api.php.

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail_url' => 'nullable|string',
            'thumbnailUrl' => 'nullable|string',
            'category' => 'required|string',
            'level' => 'required|in:beginner,intermediate,advanced',
            'language' => 'nullable|string',
        ]);

        // Accept both creator_id and creatorId, but only admins may attribute a
        // course to someone else (e.g. the AdminPage "assign creator" form) — a
        // creator/employer overriding this for an arbitrary user id is a trust-
        // boundary violation, not a legitimate use case.
        $creatorId = $user->id;
        if ($user->role === 'admin') {
            $creatorId = $request->input('creator_id') ?? $request->input('creatorId') ?? $user->id;
        }

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

        return response()->json($this->formatCourse($course), 201);
    }

    // 3. عرض تفاصيل الدورة مع الدروس (تطابق CourseDetail)
    public function show(Course $course)
    {
        // جلب المنشئ والدروس المرتبطة بالدورة وترتيبها
        $course->load(['creator', 'lessons' => function ($query) {
            $query->orderBy('order_num', 'asc');
        }]);

        $data = $this->formatCourse($course);

        // The authenticated user's own rating for this course (null if none / guest).
        $data['myRating'] = Auth::check()
            ? optional($course->reviews()->where('user_id', Auth::id())->first())->rating
            : null;

        $data['lessons'] = $course->lessons->map(function (Lesson $lesson) {
            return [
                'id' => $lesson->id,
                'courseId' => $lesson->course_id,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'videoUrl' => $lesson->video_url,
                'pdfUrl' => $lesson->pdf_url,
                'attachmentUrl' => $lesson->attachment_url,
                'attachmentName' => $lesson->attachment_name,
                'duration' => $lesson->duration,
                'order' => $lesson->order_num,
                'createdAt' => $lesson->created_at?->toJSON(),
            ];
        });

        return response()->json($data);
    }

    // 4. جلب الدورات المميزة (Featured Courses)
    public function featured()
    {
        // سنجلب الدورات الأعلى تقييماً كمثال للدورات المميزة
        $featuredCourses = Course::with('creator')
            ->orderBy('average_rating', 'desc')
            ->take(5)
            ->get()
            ->map(fn (Course $course) => $this->formatCourse($course));

        return response()->json($featuredCourses);
    }

    // --- الدروس (Lessons) --- //

    // storeLesson() — fix bug 10: accept camelCase fields
    public function storeLesson(Request $request, Course $course)
    {
        // Course owner / employer / admin only (creators can't add to others' courses).
        $this->authorize('manageContent', $course);

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

        $userId = Auth::id();

        $review = DB::transaction(function () use ($course, $userId, $validated) {
            // One review per user per course — update their existing one if present.
            $review = $course->reviews()->updateOrCreate(
                ['user_id' => $userId],
                ['rating' => $validated['rating'], 'comment' => $validated['comment'] ?? null],
            );

            // Recompute average + count from source so it's always correct (handles edits).
            $course->update([
                'total_reviews' => $course->reviews()->count(),
                'average_rating' => round((float) $course->reviews()->avg('rating'), 2),
            ]);

            return $review;
        });

        return response()->json([
            'review' => $review->load('user'),
            'averageRating' => (float) $course->fresh()->average_rating,
            'totalReviews' => $course->fresh()->total_reviews,
        ], 201);
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
        // Owner-or-admin enforced by CoursePolicy.
        $this->authorize('delete', $course);

        $course->delete();

        return response()->json(['message' => 'Course deleted successfully']);
    }

    // 9. تحديث دورة (فقط منشئها أو admin)
    public function updateCourse(Request $request, Course $course)
    {
        // Owner-or-admin enforced by CoursePolicy.
        $this->authorize('update', $course);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'thumbnail_url' => 'nullable|url',
            'thumbnailUrl' => 'nullable|url',
            'category' => 'nullable|string',
            'level' => 'nullable|in:beginner,intermediate,advanced',
        ]);

        if (isset($validated['thumbnailUrl'])) {
            $validated['thumbnail_url'] = $validated['thumbnailUrl'];
            unset($validated['thumbnailUrl']);
        }

        $course->update($validated);
        $course->load('creator');

        return response()->json($this->formatCourse($course));
    }

    // Disable/enable a course (creator-owned, or employer/admin)
    public function toggleActive(Request $request, Course $course)
    {
        $this->authorize('toggleActive', $course);
        $course->update(['is_active' => ! $course->is_active]);

        return response()->json(['success' => true, 'is_active' => $course->is_active]);
    }

    // Edit a lesson (course owner / employer / admin)
    public function updateLesson(Request $request, Course $course, Lesson $lesson)
    {
        $this->authorize('manageContent', $course);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'video_url' => 'sometimes|nullable|string',
            'videoUrl' => 'sometimes|nullable|string',
            'pdf_url' => 'sometimes|nullable|string',
            'pdfUrl' => 'sometimes|nullable|string',
            'attachment_url' => 'sometimes|nullable|string',
            'attachmentUrl' => 'sometimes|nullable|string',
            'attachment_name' => 'sometimes|nullable|string',
            'attachmentName' => 'sometimes|nullable|string',
            'duration' => 'sometimes|nullable|integer',
            'order_num' => 'sometimes|nullable|integer',
            'order' => 'sometimes|nullable|integer',
        ]);

        // Normalise camelCase → snake_case.
        $map = [
            'videoUrl' => 'video_url', 'pdfUrl' => 'pdf_url',
            'attachmentUrl' => 'attachment_url', 'attachmentName' => 'attachment_name',
            'order' => 'order_num',
        ];
        foreach ($map as $camel => $snake) {
            if (array_key_exists($camel, $validated)) {
                $validated[$snake] = $validated[$camel];
                unset($validated[$camel]);
            }
        }

        $lesson->update($validated);

        return response()->json($lesson->fresh());
    }

    // 10. حذف درس (منشئ الدورة أو employer أو admin)
    public function deleteLesson(Request $request, Course $course, Lesson $lesson)
    {
        // Managing a course's lessons requires owner / employer / admin.
        $this->authorize('manageContent', $course);

        $lesson->delete();
        $course->decrement('total_lessons');

        return response()->json(['message' => 'Lesson deleted successfully']);
    }
}

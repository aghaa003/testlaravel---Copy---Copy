<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\HomeReviewController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// فحص حالة النظام الفني (Health Check)
Route::get('/healthz', function () {
    return response()->json(['status' => 'ok']);
});

// مسارات المستخدمين ولوحة الشرف (Users & Leaderboard)
Route::get('/users/leaderboard', [UserController::class, 'leaderboard']);
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{user}', [UserController::class, 'show']);

// مسارات التحميل (File Upload) - يتطلب المصادقة
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/upload', [UploadController::class, 'store']);
    Route::post('/upload/multiple', [UploadController::class, 'storeMultiple']);
});

// مسارات الدورات والدروس والتقييمات (Courses, Lessons & Reviews)
Route::get('/courses/featured', [CourseController::class, 'featured']);
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);
Route::get('/courses/{course}/reviews', [CourseController::class, 'reviews']);

// مسارات كتابة الدورات والدروس والتقييمات - يتطلب المصادقة (creator, employer, admin فقط)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/courses', [CourseController::class, 'store']); // role: creator|employer|admin
    Route::post('/courses/{course}/lessons', [CourseController::class, 'storeLesson']); // role: creator|employer|admin
    Route::post('/courses/{course}/reviews', [CourseController::class, 'storeReview']); // any authenticated user

    // ✅ معالجة المحتوى - حذف وتعديل الدورات والدروس (creator يعدل ملكه فقط، admin يعدل الكل)
    Route::delete('/courses/{course}', [CourseController::class, 'destroyCourse']); // creator (own) or admin
    Route::put('/courses/{course}', [CourseController::class, 'updateCourse']); // creator (own) or admin
    Route::delete('/courses/{course}/lessons/{lesson}', [CourseController::class, 'deleteLesson']); // creator (own) or admin
});

// مسارات التسجيل في الدورات (Course Enrollment) - يتطلب المصادقة
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/courses/{course}/enroll', [EnrollmentController::class, 'enroll']);
    Route::get('/enrollments', [EnrollmentController::class, 'index']);
    Route::post('/courses/{course}/progress', [EnrollmentController::class, 'updateProgress']);
    Route::get('/courses/{course}/progress', [EnrollmentController::class, 'getProgress']);
    Route::get('/courses/{course}/viewers', [EnrollmentController::class, 'getViewers']);

    // مسارات الدروس والتعليقات والإعجابات (Lessons, Comments & Likes)
    Route::get('/lessons/{lesson}/progress', [LessonController::class, 'getProgress']);
    Route::post('/lessons/{lesson}/progress', [LessonController::class, 'updateProgress']);
    Route::get('/lessons/{lesson}/comments', [LessonController::class, 'getComments']);
    Route::post('/lessons/{lesson}/comments', [LessonController::class, 'storeComment']);
    Route::delete('/lessons/{lesson}/comments/{comment}', [LessonController::class, 'deleteComment']);
    Route::get('/lessons/{lesson}/like', [LessonController::class, 'getLike']);
    Route::post('/lessons/{lesson}/like', [LessonController::class, 'toggleLike']);
    Route::get('/courses/{course}/lessons-progress', [LessonController::class, 'getCourseProgress']);
});

// مسارات المجتمع والمنشورات والتعليقات (Community Posts & Comments)
Route::get('/community/posts', [CommunityController::class, 'getPosts']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/community/posts', [CommunityController::class, 'storePost']);
    Route::post('/community/posts/{post}/like', [CommunityController::class, 'togglePostLike']);
    Route::get('/community/posts/{post}/comments', [CommunityController::class, 'getComments']);
    Route::post('/community/posts/{post}/comments', [CommunityController::class, 'storeComment']);
    Route::delete('/community/posts/{post}/comments/{comment}', [CommunityController::class, 'deleteComment']);
});

// مسارات الإخطارات (Notifications)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications', [NotificationController::class, 'deleteAll']);
});

// مسارات التقييمات الرئيسية (Home Reviews)
Route::get('/home-reviews', [HomeReviewController::class, 'index']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/home-reviews', [HomeReviewController::class, 'store']); // any authenticated user

    // ✅ الموافقة على التقييمات - فقط employer و admin
    Route::post('/home-reviews/{review}/approve', [HomeReviewController::class, 'approve']); // role: employer|admin
    Route::post('/home-reviews/{review}/reject', [HomeReviewController::class, 'reject']); // role: employer|admin
});
// مسارات المستودعات والمشاريع (Repositories)
Route::get('/repositories/featured', [RepositoryController::class, 'featured']);
Route::get('/repositories', [RepositoryController::class, 'index']);
Route::get('/repositories/{repository}', [RepositoryController::class, 'show']);

// مسارات كتابة المستودعات - يتطلب المصادقة (all users, creators, employers, admins)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/repositories', [RepositoryController::class, 'store']); // role: user|creator|employer|admin
    Route::post('/repositories/{repository}/like', [RepositoryController::class, 'toggleLike']);
});

// مسارات التحديات - قراءة بدون مصادقة، كتابة تتطلب مصادقة
Route::get('/challenges', [ChallengeController::class, 'index']);
Route::get('/challenges/{challenge}', [ChallengeController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/challenges', [ChallengeController::class, 'store']); // role: creator|employer|admin
    Route::post('/challenges/{challenge}/submit', [ChallengeController::class, 'submit']); // any authenticated user

    // ✅ معالجة المحتوى - حذف وتعديل التحديات (creator يعدل ملكه فقط، admin يعدل الكل)
    Route::delete('/challenges/{challenge}', [ChallengeController::class, 'deleteChallenge']); // creator (own) or admin
    Route::put('/challenges/{challenge}', [ChallengeController::class, 'updateChallenge']); // creator (own) or admin
});

// مسارات التكليفات ومراجعة الذكاء الاصطناعي (Assignments & AI Review)
Route::get('/courses-with-assignments', [AssignmentController::class, 'coursesWithAssignments']);
Route::get('/assignments', [AssignmentController::class, 'index']);
Route::get('/assignments/{assignment}', [AssignmentController::class, 'show']);

// مراجعة الكود عبر الذكاء الاصطناعي - بدون حاجة لمصادقة (تستخدمه ProjectsPage قبل تسجيل الدخول)
Route::post('/assignments/review', [AssignmentController::class, 'review']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/assignments', [AssignmentController::class, 'store']); // role: employer|admin (NOT creator)
    Route::post('/assignments/submit', [AssignmentController::class, 'submit']); // any authenticated user
});

// مسارات الذكاء الاصطناعي عبر Ollama
Route::post('/ai/helper', [AiController::class, 'general']);
Route::post('/ai/helper-challenges', [AiController::class, 'challenges']);
Route::post('/ai/helper-projects', [AiController::class, 'projects']);

// مسار البحث الشامل للمنصة (Global Search)
Route::get('/search', [SearchController::class, 'index']);

// مسارات الإحصائيات (Platform & User Stats)
Route::get('/stats/platform', [StatsController::class, 'platform']);
Route::get('/stats/user/{user}', [StatsController::class, 'userStats']);

// 🔒 مسارات محمية - تتطلب مصادقة Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // ✅ مسارات المصادقة والملف الشخصي
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/users/profile', [UserController::class, 'profile']);
    Route::put('/users/profile', [UserController::class, 'updateProfile']);
    Route::put('/users/{user}', [UserController::class, 'update']);

    // ✅ مسارات الإخطارات (مكررة من الأعلى - تُترك هنا للتوضيح)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications', [NotificationController::class, 'deleteAll']);

    // 🔐 مسارات المعالجة والاعتدال (Employer & Admin) - الموافقة على المحتوى
    Route::get('/employer/courses', [ModerationController::class, 'getCourses']);
    Route::get('/employer/assignments', [ModerationController::class, 'getAssignments']);
    Route::get('/employer/comments', [ModerationController::class, 'getComments']);
    Route::delete('/employer/comments/{type}/{id}', [ModerationController::class, 'deleteComment']);
    Route::get('/employer/reviews', [ModerationController::class, 'getReviews']);
    Route::post('/employer/reviews/{review}/approve', [ModerationController::class, 'approveReview']);
    Route::post('/employer/reviews/{review}/reject', [ModerationController::class, 'rejectReview']);

    // 🔐 مسارات الإدارة الشاملة (Admin Only) - admin لديه السيطرة الكاملة على كل شيء
    Route::get('/admin/logs', [AdminController::class, 'getLogs']);
    Route::get('/admin/engagements', [AdminController::class, 'getEngagements']);
    Route::get('/admin/courses', [ModerationController::class, 'getCourses']); // admin moderation
    Route::get('/admin/assignments', [ModerationController::class, 'getAssignments']); // admin moderation
    Route::get('/admin/comments', [AdminController::class, 'getComments']); // admin moderation
    Route::delete('/admin/comments/{type}/{id}', [AdminController::class, 'deleteComment']); // admin moderation
    Route::get('/admin/reviews', [AdminController::class, 'getReviews']); // admin moderation
    Route::post('/admin/reviews/{review}/approve', [AdminController::class, 'approveReview']); // admin moderation
    Route::post('/admin/reviews/{review}/reject', [AdminController::class, 'rejectReview']); // admin moderation
    Route::post('/admin/users/{user}/ban', [AdminController::class, 'banUser']);
    Route::post('/admin/users/{user}/unban', [AdminController::class, 'unbanUser']);
});

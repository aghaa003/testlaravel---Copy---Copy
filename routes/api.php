<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ExampleController;
use App\Http\Controllers\HomeReviewController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Health check
Route::get('/healthz', fn () => response()->json(['status' => 'ok']));

// ── Public read-only routes ──────────────────────────────────────────────────
Route::get('/users/leaderboard', [UserController::class, 'leaderboard']);
Route::get('/users/{user}', [UserController::class, 'show']);
Route::get('/users/{user}/courses', [UserController::class, 'courses']);

Route::get('/courses/featured', [CourseController::class, 'featured']);
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);
Route::get('/courses/{course}/reviews', [CourseController::class, 'reviews']);

Route::get('/lessons/{lesson}/comments', [LessonController::class, 'getComments']);
Route::get('/lessons/{lesson}/likes', [LessonController::class, 'getLikes']);
Route::get('/lessons/{lesson}/like', [LessonController::class, 'getLike']);

Route::get('/repositories/featured', [RepositoryController::class, 'featured']);
Route::get('/repositories', [RepositoryController::class, 'index']);
Route::get('/repositories/{repository}', [RepositoryController::class, 'show']);

Route::get('/challenges', [ChallengeController::class, 'index']);
Route::middleware('auth:sanctum')->get('/challenges/my-submissions', [ChallengeController::class, 'mySubmissions']);
Route::get('/challenges/{challenge}', [ChallengeController::class, 'show']);

Route::get('/community/posts', [CommunityController::class, 'getPosts']);
Route::get('/community/posts/{post}/comments', [CommunityController::class, 'getComments']);

Route::get('/home-reviews', [HomeReviewController::class, 'index']);

Route::get('/examples', [ExampleController::class, 'index']);

Route::get('/projects', [ProjectController::class, 'index']);

Route::get('/courses-with-assignments', [AssignmentController::class, 'coursesWithAssignments']);
Route::get('/assignments', [AssignmentController::class, 'index']);
Route::get('/assignments/{assignment}', [AssignmentController::class, 'show']);

Route::get('/search', [SearchController::class, 'index']);
Route::get('/stats/platform', [StatsController::class, 'platform']);
Route::get('/stats/user/{user}', [StatsController::class, 'userStats']);

// AI code review — no auth but rate limited
Route::middleware('throttle:30,1')->post('/assignments/review', [AssignmentController::class, 'review']);

// ── AI routes — 20/min ───────────────────────────────────────────────────────
Route::middleware('throttle:20,1')->group(function () {
    Route::post('/ai/helper', [AiController::class, 'general']);
    Route::post('/ai/helper-challenges', [AiController::class, 'challenges']);
    Route::post('/ai/helper-projects', [AiController::class, 'projects']);
});

// ── Upload — auth + 30/min ───────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::post('/upload', [UploadController::class, 'store']);
    Route::post('/upload/multiple', [UploadController::class, 'storeMultiple']);
});

// ── All authenticated write routes ───────────────────────────────────────────
// Role gating is declared here via the `role:` middleware (see RoleMiddleware).
// Routes whose access depends on record ownership (edit your own course/repo, or
// admin) are left ungated here and enforced by Policies inside the controllers.
Route::middleware('auth:sanctum')->group(function () {

    // Auth & profile (any authenticated user)
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/users/profile', [UserController::class, 'profile']);
    Route::match(['put', 'post'], '/users/profile', [UserController::class, 'updateProfile']);

    // Enrollment & progress (any authenticated user)
    Route::post('/courses/{course}/enroll', [EnrollmentController::class, 'enroll']);
    Route::get('/enrollments', [EnrollmentController::class, 'index']);
    Route::post('/courses/{course}/progress', [EnrollmentController::class, 'updateProgress']);
    Route::get('/courses/{course}/progress', [EnrollmentController::class, 'getProgress']);
    Route::get('/courses/{course}/viewers', [EnrollmentController::class, 'getViewers']);

    // Lessons (any authenticated user; comment delete is owner-or-admin via Policy)
    Route::get('/lessons/{lesson}/progress', [LessonController::class, 'getProgress']);
    Route::post('/lessons/{lesson}/progress', [LessonController::class, 'updateProgress']);
    Route::post('/lessons/{lesson}/comments', [LessonController::class, 'storeComment']);
    Route::delete('/lessons/{lesson}/comments/{comment}', [LessonController::class, 'deleteComment']);
    Route::post('/lessons/{lesson}/like', [LessonController::class, 'toggleLike']);
    Route::get('/courses/{course}/lessons-progress', [LessonController::class, 'getCourseProgress']);

    // Repositories — create is open; update/destroy are owner-or-admin via Policy
    Route::post('/repositories', [RepositoryController::class, 'store']);
    Route::put('/repositories/{repository}', [RepositoryController::class, 'update']);
    Route::delete('/repositories/{repository}', [RepositoryController::class, 'destroy']);
    Route::post('/repositories/{repository}/like', [RepositoryController::class, 'toggleLike']);

    // Challenges — submit is open; update/delete are owner-or-admin via Policy
    Route::post('/challenges/{challenge}/submit', [ChallengeController::class, 'submit']);
    Route::put('/challenges/{challenge}', [ChallengeController::class, 'updateChallenge']);
    Route::delete('/challenges/{challenge}', [ChallengeController::class, 'deleteChallenge']);

    // Courses — review CRUD + ownership-gated mutations (Policy inside controller)
    Route::post('/courses/{course}/reviews', [CourseController::class, 'storeReview']);
    Route::delete('/courses/{course}/reviews/{review}', [CourseController::class, 'destroyReview']);
    Route::put('/courses/{course}', [CourseController::class, 'updateCourse']);
    Route::delete('/courses/{course}', [CourseController::class, 'destroyCourse']);
    Route::delete('/courses/{course}/lessons/{lesson}', [CourseController::class, 'deleteLesson']);

    // Assignment submission (any authenticated user)
    Route::post('/assignments/submit', [AssignmentController::class, 'submit']);

    // Community (any authenticated user; comment delete is owner-or-admin via Policy)
    Route::post('/community/posts', [CommunityController::class, 'storePost']);
    Route::post('/community/posts/{post}/like', [CommunityController::class, 'togglePostLike']);
    Route::post('/community/posts/{post}/comments', [CommunityController::class, 'storeComment']);
    Route::delete('/community/posts/{post}/comments/{comment}', [CommunityController::class, 'deleteComment']);

    // Notifications (owner-scoped inside controller)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications', [NotificationController::class, 'deleteAll']);

    // Home reviews — anyone may submit; only employer/admin may moderate
    Route::post('/home-reviews', [HomeReviewController::class, 'store']);

    // ── Content creators (creator / employer / admin) ──────────────────────────
    Route::middleware('role:creator,employer,admin')->group(function () {
        Route::post('/courses', [CourseController::class, 'store']);
        Route::post('/courses/{course}/lessons', [CourseController::class, 'storeLesson']);
        Route::post('/challenges', [ChallengeController::class, 'store']);
    });

    // ── Employer / admin (content moderation & management) ─────────────────────
    Route::middleware('role:employer,admin')->group(function () {
        // Assignments
        Route::post('/assignments', [AssignmentController::class, 'store']);
        Route::put('/assignments/{assignment}', [AssignmentController::class, 'update']);
        Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy']);

        // Examples
        Route::post('/examples', [ExampleController::class, 'store']);
        Route::match(['put', 'patch'], '/examples/{example}', [ExampleController::class, 'update']);
        Route::delete('/examples/{example}', [ExampleController::class, 'destroy']);

        // Projects
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

        // Home review moderation
        Route::post('/home-reviews/{review}/approve', [HomeReviewController::class, 'approve']);
        Route::post('/home-reviews/{review}/reject', [HomeReviewController::class, 'reject']);

        // Employer moderation dashboard
        Route::get('/employer/courses', [ModerationController::class, 'getCourses']);
        Route::get('/employer/assignments', [ModerationController::class, 'getAssignments']);
        Route::get('/employer/comments', [ModerationController::class, 'getComments']);
        Route::delete('/employer/comments/{type}/{id}', [ModerationController::class, 'deleteComment']);
        Route::get('/employer/reviews', [ModerationController::class, 'getReviews']);
        Route::post('/employer/reviews/{review}/approve', [ModerationController::class, 'approveReview']);
        Route::post('/employer/reviews/{review}/reject', [ModerationController::class, 'rejectReview']);

        // Admin dashboard data shared with employers
        Route::get('/admin/courses', [ModerationController::class, 'getCourses']);
        Route::get('/admin/assignments', [ModerationController::class, 'getAssignments']);
        Route::get('/admin/reviews', [AdminController::class, 'getReviews']);
        Route::post('/admin/reviews/{review}/approve', [AdminController::class, 'approveReview']);
        Route::post('/admin/reviews/{review}/reject', [AdminController::class, 'rejectReview']);
    });

    // ── Admin only ─────────────────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update']);

        Route::get('/admin/logs', [AdminController::class, 'getLogs']);
        Route::get('/admin/engagements', [AdminController::class, 'getEngagements']);
        Route::get('/admin/comments', [AdminController::class, 'getComments']);
        Route::delete('/admin/comments/{id}', [AdminController::class, 'deleteCommentById'])->whereNumber('id');
        Route::delete('/admin/comments/{type}/{id}', [AdminController::class, 'deleteComment'])
            ->where('type', 'lesson|community')->whereNumber('id');
        Route::post('/admin/users/{user}/ban', [AdminController::class, 'banUser']);
        Route::post('/admin/users/{user}/unban', [AdminController::class, 'unbanUser']);
    });
});

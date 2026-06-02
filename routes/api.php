<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StatsController;
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
Route::apiResource('users', UserController::class)->only(['index', 'store', 'show', 'update']);

// مسارات الدورات والدروس والتقييمات (Courses, Lessons & Reviews)
Route::get('/courses/featured', [CourseController::class, 'featured']);
Route::apiResource('courses', CourseController::class)->only(['index', 'store', 'show']);
Route::post('/courses/{course}/lessons', [CourseController::class, 'storeLesson']);
Route::get('/courses/{course}/reviews', [CourseController::class, 'reviews']);
Route::post('/courses/{course}/reviews', [CourseController::class, 'storeReview']);

// مسارات المستودعات والمشاريع والإعجابات (Repositories & Likes)
Route::get('/repositories/featured', [RepositoryController::class, 'featured']);
Route::apiResource('repositories', RepositoryController::class)->only(['index', 'store', 'show']);
Route::post('/repositories/{repository}/like', [RepositoryController::class, 'toggleLike']);

// مسارات التحديات والحلول (Challenges & Submissions)
Route::apiResource('challenges', ChallengeController::class)->only(['index', 'store', 'show']);

// مسارات التكليفات ومراجعة الذكاء الاصطناعي (Assignments & AI Review)
Route::get('/courses-with-assignments', [AssignmentController::class, 'coursesWithAssignments']);
Route::apiResource('assignments', AssignmentController::class)->only(['index', 'store', 'show']);

Route::post('/assignments/review', [AssignmentController::class, 'review']);

// مسارات الذكاء الاصطناعي عبر Ollama
Route::post('/ai/helper', [AiController::class, 'general']);
Route::post('/ai/helper-challenges', [AiController::class, 'challenges']);
Route::post('/ai/helper-projects', [AiController::class, 'projects']);

// مسار البحث الشامل للمنصة (Global Search)
Route::get('/search', [SearchController::class, 'index']);

// مسارات الإحصائيات (Platform & User Stats)
Route::get('/stats/platform', [StatsController::class, 'platform']);
Route::get('/stats/user/{user}', [StatsController::class, 'userStats']);

// مسارات محمية (يجب أن يكون المستخدم مسجل الدخول للوصول إليها)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']); // ✅ correct
    Route::get('/users/profile', [UserController::class, 'profile']);
    Route::put('/users/profile', [UserController::class, 'updateProfile']);
    Route::post('/assignments/submit', [AssignmentController::class, 'submit']);
    Route::post('/challenges/{challenge}/submit', [ChallengeController::class, 'submit']);

});

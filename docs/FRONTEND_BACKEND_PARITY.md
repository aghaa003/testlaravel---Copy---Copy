# Frontend ↔ Laravel backend parity (full audit)

**Date:** 2026-06-03  
**Laravel:** `testlaravel - Copy - Copy`  
**Frontend:** `academy_clean/artifacts/academy` (+ `@workspace/api-client-react` hooks)

## Summary

| Category | Count |
|----------|-------|
| SPA `fetch()` API paths | 38 unique patterns |
| OpenAPI-generated hooks | 30 operations (subset of SPA) |
| Laravel `api/*` + `web` auth routes | All SPA paths covered ✅ |
| New this pass | `POST /users/points`, `DELETE /courses/{course}/reviews/{review}` |

---

## SPA manual `fetch()` → Laravel

| Method | SPA path | Laravel | Status |
|--------|----------|---------|--------|
| POST | `/api/register` | `web` AuthController@register | ✅ |
| POST | `/api/login` | `web` AuthController@login | ✅ |
| GET | `/api/auth/me` | AuthController@me | ✅ |
| POST | `/api/logout` | AuthController@logout | ✅ |
| GET | `/api/users/profile` | UserController@profile | ✅ |
| POST | `/api/users/profile` | UserController@updateProfile | ✅ |
| PATCH | `/api/users/{id}` | UserController@update (admin) | ✅ |
| GET | `/api/users?limit=` | UserController@index | ✅ |
| GET | `/api/users/{id}` | UserController@show | ✅ |
| POST | `/api/users/points` | UserController@addPoints | ✅ **new** |
| POST | `/api/upload` | UploadController@store | ✅ |
| POST | `/api/upload/multiple` | UploadController@storeMultiple | ✅ |
| GET | `/api/repositories?userId=` | RepositoryController@index | ✅ |
| POST | `/api/repositories` | RepositoryController@store | ✅ |
| DELETE | `/api/repositories/{id}` | RepositoryController@destroy | ✅ |
| GET/POST | `/api/courses` | CourseController | ✅ |
| GET/PUT/DELETE | `/api/courses/{id}` | CourseController | ✅ |
| POST | `/api/courses/{id}/lessons` | CourseController@storeLesson | ✅ |
| DELETE | `/api/courses/{id}/lessons/{lessonId}` | CourseController@deleteLesson | ✅ |
| GET | `/api/courses/{id}/viewers` | EnrollmentController@getViewers | ✅ |
| GET/POST | `/api/courses/{id}/progress` | EnrollmentController | ✅ |
| DELETE | `/api/courses/{id}/reviews/{reviewId}` | CourseController@destroyReview | ✅ **new** (creator only) |
| GET | `/api/lessons/{id}/comments` | LessonController@getComments (public) | ✅ |
| POST | `/api/lessons/{id}/comments` | LessonController@storeComment | ✅ |
| GET | `/api/lessons/{id}/likes` | LessonController@getLikes (public) | ✅ |
| POST | `/api/lessons/{id}/like` | LessonController@toggleLike | ✅ |
| GET/POST | `/api/home-reviews` | HomeReviewController | ✅ |
| GET/POST | `/api/community/posts` | CommunityController | ✅ |
| GET/POST | `/api/community/posts/{id}/comments` | CommunityController | ✅ |
| POST | `/api/community/posts/{id}/like` | CommunityController | ✅ |
| GET | `/api/notifications` | NotificationController@index | ✅ |
| POST | `/api/notifications/read-all` | NotificationController | ✅ |
| GET | `/api/search?q=` | SearchController@index | ✅ |
| POST | `/api/assignments/review` | AssignmentController@review | ✅ |
| POST | `/api/challenges/{id}/submit` | ChallengeController@submit | ✅ |
| GET | `/api/admin/logs` | AdminController@getLogs | ✅ |
| GET | `/api/admin/engagements` | AdminController@getEngagements | ✅ |
| GET | `/api/admin/comments` | AdminController@getComments | ✅ |
| DELETE | `/api/admin/comments/{id}` | AdminController@deleteCommentById | ✅ |
| GET | `/api/admin/reviews` | AdminController@getReviews | ✅ |
| POST | `/api/admin/reviews/{id}/approve` | AdminController@approveReview | ✅ |
| POST | `/api/admin/reviews/{id}/reject` | AdminController@rejectReview (reason optional) | ✅ |
| POST | `/api/admin/users/{id}/ban\|unban` | AdminController | ✅ |

---

## React Query hooks (OpenAPI) → Laravel

Hooks used in pages: `useListCourses`, `useGetCourse`, `useListChallenges`, `useGetLeaderboard`, `useGetPlatformStats`, `useGetFeaturedRepositories`, `useListRepositories`, `useListUsers`, `useGetUserStats`, `useCreateCourse`, `useCreateChallenge`, `useUpdateUser`, `useGlobalSearch`.

All map to existing Laravel routes. **Note:** OpenAPI `User` schema still lists `clerkId` (legacy); Laravel returns UUID `id` without `clerkId` — hooks work if pages only use `id`, `name`, `role`.

---

## Laravel routes not called by SPA today (ready for future UI)

| Route | Purpose |
|-------|---------|
| `POST /api/users/points` | Award points to current user |
| `DELETE /api/courses/{course}/reviews/{review}` | Creator removes course review |
| `GET /api/employer/*` | Employer moderation |
| `POST /api/home-reviews/{review}/approve\|reject` | Moderate home reviews |
| `POST /api/ai/helper*` | AI helpers |
| `POST /api/courses/{course}/enroll` | Enrollment |
| `GET /api/enrollments` | User enrollments |
| `GET /api/healthz` | Health check |

---

## Intentionally not ported (Node-only)

- `POST /api/users` Clerk upsert  
- `GET /api/challenges/seed`  
- Duplicate admin paths (`/admin/login-logs`, `/admin/engagement`, …)

---

## Permission notes

- **`DELETE /courses/{course}/reviews/{review}`:** only `course.creator_id === auth user` (not review author, not admin).
- **`POST /users/points`:** increments **authenticated user's** points (`points` must be ≥ 1).

---

## How to re-verify

```powershell
cd "c:\Users\aghaa\Desktop\testlaravel - Copy - Copy"
php artisan route:list | findstr /i "api"
```

Regenerate client (optional):

```bash
cd academy_clean/lib/api-spec && pnpm run codegen
```

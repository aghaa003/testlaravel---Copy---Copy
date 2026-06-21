<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RepositoryController extends Controller
{
    // GET /api/repositories
    public function index(Request $request)
    {
        $query = Repository::with('owner')->withAvg('ratings', 'rating')->withCount('ratings');
        $viewerId = Auth::id();
        $userId = $request->input('userId') ?? $request->input('user_id');

        if ($userId) {
            $query->where('owner_id', $userId);
            // Only show private repos to the owner themselves
            if ($viewerId !== $userId) {
                $query->where('visibility', 'public')
                    ->where('is_draft', false);
            }
        } else {
            $query->where('visibility', 'public')
                ->where('is_draft', false);
        }

        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);
        $total = $query->count();

        $repositories = $query
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'repositories' => $repositories,
            'total' => $total,
        ]);
    }

    // POST /api/repositories
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'technologies' => 'required|array',
        ]);

        // ✅ Fix 1+2: resolve owner from auth, accept camelCase fields
        $ownerId = $request->input('owner_id') ?? $request->input('userId') ?? $user->id;
        $isPublic = $request->boolean('isPublic', $request->boolean('is_public', true));
        $isDraft = $request->boolean('isDraft', $request->boolean('is_draft', false));
        $visibility = $isPublic ? 'public' : 'private';

        $codeFiles = $request->input('code_files_urls') ?? $request->input('codeFilesUrls') ?? [];
        $pdfFiles = $request->input('pdf_files_urls') ?? $request->input('pdfFilesUrls') ?? [];
        $githubUrl = $request->input('github_url') ?? $request->input('githubUrl') ?? $request->input('repoUrl');

        if (! $isDraft) {
            $effortError = $this->effortCheckError($codeFiles, $pdfFiles, $githubUrl, $request->input('description', ''));
            if ($effortError) {
                return response()->json(['error' => $effortError], 422);
            }
        }

        $repository = Repository::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'thumbnail_url' => $request->input('thumbnail_url') ?? $request->input('thumbnailUrl'),
            'owner_id' => $ownerId,
            'technologies' => $request->input('technologies'),
            'visibility' => $visibility,
            'project_url' => $request->input('project_url') ?? $request->input('projectUrl'),
            'github_url' => $request->input('github_url') ?? $request->input('githubUrl') ?? $request->input('repoUrl'),
            'is_draft' => $isDraft,
            'live_demo_url' => $request->input('live_demo_url') ?? $request->input('liveDemoUrl'),
            'cover_image_url' => $request->input('cover_image_url') ?? $request->input('coverImageUrl'),
            'code_files_urls' => $codeFiles,
            'pdf_files_urls' => $pdfFiles,
            'source_project' => $request->input('source_project') ?? $request->input('sourceProject'),
        ]);

        $repository->load('owner');

        return response()->json($repository, 201);
    }

    // PUT /api/repositories/{repository}
    public function update(Request $request, Repository $repository)
    {
        // Owner-or-admin enforced by RepositoryPolicy.
        $this->authorize('update', $repository);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'technologies' => 'sometimes|array',
        ]);

        $updates = [];

        if ($request->has('title')) {
            $updates['title'] = $request->input('title');
        }
        if ($request->has('description')) {
            $updates['description'] = $request->input('description');
        }
        if ($request->has('technologies')) {
            $updates['technologies'] = $request->input('technologies');
        }
        if ($request->has('repoUrl') || $request->has('github_url') || $request->has('githubUrl')) {
            $updates['github_url'] = $request->input('github_url') ?? $request->input('githubUrl') ?? $request->input('repoUrl');
        }
        if ($request->has('liveDemoUrl') || $request->has('live_demo_url')) {
            $updates['live_demo_url'] = $request->input('live_demo_url') ?? $request->input('liveDemoUrl');
        }
        if ($request->has('coverImageUrl') || $request->has('cover_image_url')) {
            $updates['cover_image_url'] = $request->input('cover_image_url') ?? $request->input('coverImageUrl');
        }
        if ($request->has('codeFilesUrls') || $request->has('code_files_urls')) {
            $updates['code_files_urls'] = $request->input('code_files_urls') ?? $request->input('codeFilesUrls') ?? [];
        }
        if ($request->has('pdfFilesUrls') || $request->has('pdf_files_urls')) {
            $updates['pdf_files_urls'] = $request->input('pdf_files_urls') ?? $request->input('pdfFilesUrls') ?? [];
        }
        if ($request->has('isPublic') || $request->has('is_public')) {
            $isPublic = $request->boolean('isPublic', $request->boolean('is_public', true));
            $updates['visibility'] = $isPublic ? 'public' : 'private';
        }
        if ($request->has('isDraft') || $request->has('is_draft')) {
            $updates['is_draft'] = $request->boolean('isDraft', $request->boolean('is_draft', false));
        }

        // Same publish guard as store(): cannot leave draft status without
        // real evidence of effort (existing files/link/description, or ones in this request).
        $willBeDraft = $updates['is_draft'] ?? $repository->is_draft;
        if (! $willBeDraft) {
            $codeFiles = $updates['code_files_urls'] ?? $repository->code_files_urls;
            $pdfFiles = $updates['pdf_files_urls'] ?? $repository->pdf_files_urls;
            $githubUrl = $updates['github_url'] ?? $repository->github_url;
            $description = $updates['description'] ?? $repository->description;
            $effortError = $this->effortCheckError($codeFiles, $pdfFiles, $githubUrl, $description);
            if ($effortError) {
                return response()->json(['error' => $effortError], 422);
            }
        }

        $repository->update($updates);
        $repository->load('owner');

        return response()->json($repository);
    }

    // GET /api/repositories/{repository}
    public function show(Repository $repository)
    {
        $repository->load('owner')->loadAvg('ratings', 'rating')->loadCount('ratings');

        return response()->json($repository);
    }

    // GET /api/repositories/featured — the 6 highest-rated public repositories.
    public function featured()
    {
        $featured = Repository::with('owner')
            ->withAvg('ratings', 'rating')
            ->withCount('ratings')
            ->where('visibility', 'public')
            ->where('is_draft', false)
            ->orderByDesc('ratings_avg_rating')
            ->orderByDesc('likes_count') // tie-break: unrated repos / equal averages fall back to likes
            ->take(6)
            ->get();

        return response()->json($featured);
    }

    // POST /api/repositories/{repository}/rate — body: {rating: 1-5}. Owners cannot rate their own repo.
    public function rate(Request $request, Repository $repository)
    {
        $userId = Auth::id();
        if (! $userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if ($repository->owner_id === $userId) {
            return response()->json(['error' => 'لا يمكنك تقييم مشروعك الخاص.'], 403);
        }

        $validated = $request->validate(['rating' => 'required|integer|min:1|max:5']);

        $alreadyRated = DB::table('repository_ratings')
            ->where('user_id', $userId)->where('repository_id', $repository->id)->exists();

        DB::table('repository_ratings')->updateOrInsert(
            ['user_id' => $userId, 'repository_id' => $repository->id],
            ['rating' => $validated['rating'], 'updated_at' => now(), 'created_at' => now()]
        );

        if (! $alreadyRated) {
            $rater = \App\Models\User::find($userId);
            Notification::create([
                'user_id' => $repository->owner_id, 'from_user_id' => $userId,
                'from_user_name' => $rater?->name, 'title' => 'تقييم جديد لمشروعك',
                'type' => 'repo_rating', 'entity_id' => $repository->id,
                'entity_title' => $repository->title,
                'message' => "{$rater?->name} قيّم مشروعك \"{$repository->title}\" بـ {$validated['rating']} نجوم",
            ]);
        }

        $repository->loadAvg('ratings', 'rating')->loadCount('ratings');

        return response()->json([
            'averageRating' => round((float) $repository->ratings_avg_rating, 1),
            'ratingsCount' => $repository->ratings_count,
            'yourRating' => $validated['rating'],
        ]);
    }

    // POST /api/repositories/{repository}/like
    public function toggleLike(Request $request, Repository $repository)
    {
        // ✅ Fix 3: always use Auth::id(), ignore user_id from request
        $userId = Auth::id();

        if (! $userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $existing = DB::table('repository_likes')
            ->where('user_id', $userId)
            ->where('repository_id', $repository->id)
            ->first();

        if ($existing) {
            DB::table('repository_likes')
                ->where('user_id', $userId)
                ->where('repository_id', $repository->id)
                ->delete();
            $repository->decrement('likes_count');
            $liked = false;
        } else {
            DB::table('repository_likes')->insert([
                'user_id' => $userId,
                'repository_id' => $repository->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $repository->increment('likes_count');
            $liked = true;

            if ($repository->owner_id !== $userId) {
                $liker = \App\Models\User::find($userId);
                Notification::create([
                    'user_id' => $repository->owner_id, 'from_user_id' => $userId,
                    'from_user_name' => $liker?->name, 'title' => 'إعجاب بمشروعك',
                    'type' => 'repo_like', 'entity_id' => $repository->id,
                    'entity_title' => $repository->title, 'message' => "{$liker?->name} أعجب بمشروعك \"{$repository->title}\"",
                ]);
            }
        }

        return response()->json([
            'liked' => $liked,
            'likesCount' => $repository->fresh()->likes_count,
        ]);
    }

    // DELETE /api/repositories/{repository}
    public function destroy(Request $request, Repository $repository)
    {
        // Owner-or-admin enforced by RepositoryPolicy.
        $this->authorize('delete', $repository);

        $repository->delete();

        return response()->json(['success' => true, 'message' => 'Repository deleted']);
    }

    /**
     * Anti-cheat / effort check applied before a repository can leave draft
     * status. Accepts either real files/links, OR a sufficiently descriptive
     * write-up (project solutions are often free-form, not always literal
     * pasted code, so this is deliberately lighter than the strict
     * code-language detector used for challenges). Rejects obvious filler
     * text so a user can't just type a few characters to bypass the check.
     * Returns an Arabic error message, or null if the submission passes.
     */
    private function effortCheckError(array $codeFiles, array $pdfFiles, ?string $githubUrl, ?string $description): ?string
    {
        if (! empty($codeFiles) || ! empty($pdfFiles) || $githubUrl) {
            return null;
        }

        $trimmed = trim((string) $description);
        $minimal = mb_strlen($trimmed) < 20;

        $fillerPhrases = [
            'تم', 'done', 'test', 'تجربة', 'انتهيت', 'finished', 'ok', 'حسنا',
            'الحل صحيح', 'الكود صحيح', 'تم الحل', 'لا يوجد',
        ];
        $isFiller = in_array(mb_strtolower($trimmed), array_map('mb_strtolower', $fillerPhrases), true);

        if ($minimal || $isFiller) {
            return 'يجب إرفاق ملفات كود أو ملف PDF أو رابط GitHub، أو كتابة وصف حقيقي للحل (٢٠ حرفاً على الأقل) قبل اعتباره مكتملاً.';
        }

        return null;
    }
}

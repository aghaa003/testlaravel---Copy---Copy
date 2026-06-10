<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RepositoryController extends Controller
{
    // GET /api/repositories
    public function index(Request $request)
    {
        $query = Repository::with('owner');
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
        $user = Auth::user();

        if ($user->role !== 'admin' && $repository->owner_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

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

        $repository->update($updates);
        $repository->load('owner');

        return response()->json($repository);
    }

    // GET /api/repositories/{repository}
    public function show(Repository $repository)
    {
        $repository->load('owner');

        return response()->json($repository);
    }

    // GET /api/repositories/featured
    public function featured()
    {
        $featured = Repository::with('owner')
            ->where('visibility', 'public')
            ->where('is_draft', false)
            ->orderBy('likes_count', 'desc')
            ->take(6)
            ->get();

        return response()->json($featured);
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
        }

        return response()->json([
            'liked' => $liked,
            'likesCount' => $repository->fresh()->likes_count,
        ]);
    }

    // DELETE /api/repositories/{repository}
    public function destroy(Request $request, Repository $repository)
    {
        $user = Auth::user();

        if ($user->role !== 'admin' && $repository->owner_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $repository->delete();

        return response()->json(['success' => true, 'message' => 'Repository deleted']);
    }
}

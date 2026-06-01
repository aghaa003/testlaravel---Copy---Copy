<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use Illuminate\Http\Request;

class RepositoryController extends Controller
{
    // 1. جلب قائمة المشاريع (تطابق RepositoryListResponse)
    public function index(Request $request)
    {
        $query = Repository::with('owner');

        // تصفية المشاريع الخاصة بمستخدم معين إذا تم تمرير الـ userId
        if ($request->has('userId')) {
            $query->where('owner_id', $request->userId);
        }

        // إظهار المشاريع العامة فقط بشكل افتراضي
        $query->where('visibility', 'public');

        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $total = $query->count();
        $repositories = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'repositories' => $repositories,
            'total' => $total,
        ]);
    }

    // 2. إضافة مشروع جديد (CreateRepositoryBody)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'owner_id' => 'required|exists:users,id',
            'technologies' => 'required|array',
            'visibility' => 'in:public,private',
            'project_url' => 'nullable|string',
            'github_url' => 'nullable|string',
            'is_draft' => 'boolean',
            'live_demo_url' => 'nullable|string',
            'cover_image_url' => 'nullable|string',
            'code_files_urls' => 'nullable|array',
            'pdf_files_urls' => 'nullable|array',
            'source_project' => 'nullable|string|max:255',
        ]);

        // cast arrays to JSON strings for storage
        if (isset($validated['code_files_urls'])) {
            $validated['code_files_urls'] = json_encode($validated['code_files_urls']);
        }
        if (isset($validated['pdf_files_urls'])) {
            $validated['pdf_files_urls'] = json_encode($validated['pdf_files_urls']);
        }

        $repository = Repository::create($validated);
        $repository->load('owner');

        return response()->json($repository, 201);
    }

    // 3. عرض تفاصيل مشروع معين
    public function show(Repository $repository)
    {
        $repository->load('owner');

        return response()->json($repository);
    }

    // 4. جلب المشاريع المميزة (Featured Repositories)
    public function featured()
    {
        // جلب المشاريع الأكثر حوزاً على الإعجابات
        $featured = Repository::with('owner')
            ->where('visibility', 'public')
            ->orderBy('likes_count', 'desc')
            ->take(6)
            ->get();

        return response()->json($featured);
    }

    // 5. نظام الإعجاب بالمشروع أو إلغائه (Like / Unlike)
    public function toggleLike(Request $request, Repository $repository)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $userId = $validated['user_id'];

        $existing = \DB::table('repository_likes')
            ->where('user_id', $userId)
            ->where('repository_id', $repository->id)
            ->first();

        if ($existing) {
            \DB::table('repository_likes')
                ->where('user_id', $userId)
                ->where('repository_id', $repository->id)
                ->delete();
            $repository->decrement('likes_count');
            $liked = false;
        } else {
            \DB::table('repository_likes')->insert([
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
}

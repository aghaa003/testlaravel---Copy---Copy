<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'thumbnail_url' => 'nullable|url',
            'owner_id' => 'required|exists:users,id', // سيتم جلبها لاحقاً من الـ Auth
            'technologies' => 'required|array', // مصفوفة مثل ["React", "Tailwind"]
            'visibility' => 'in:public,private',
            'project_url' => 'nullable|url',
        ]);

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
            'user_id' => 'required|exists:users,id', // سيتم تعويضه بالـ Auth لاحقاً
        ]);

        $userId = $validated['user_id'];

        // استخدام Transaction لضمان سلامة البيانات عند التعديل في جدولين
        $liked = DB::transaction(function () use ($repository, $userId) {
            // التحقق مما إذا كان المستخدم قد أعجب بالمشروع مسبقاً
            $likeExists = $repository->likes()->where('user_id', $userId)->exists();

            if ($likeExists) {
                // إلغاء الإعجاب
                $repository->likes()->detach($userId);
                $repository->decrement('likes_count');

                return false;
            } else {
                // إضافة إعجاب جديد
                $repository->likes()->attach($userId);
                $repository->increment('likes_count');

                return true;
            }
        });

        return response()->json([
            'liked' => $liked,
            'likesCount' => $repository->likes_count,
        ]);
    }
}

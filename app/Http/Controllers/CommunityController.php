<?php

namespace App\Http\Controllers;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityPostLike;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommunityController extends Controller
{
    /**
     * GET /api/community/posts
     * Get all community posts
     */
    public function getPosts(Request $request)
    {
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);
        $category = $request->input('category');

        $query = CommunityPost::with('user', 'comments.user', 'likes');

        if ($category) {
            $query->where('category', $category);
        }

        $total = $query->count();
        $posts = $query->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'posts' => $posts,
            'total' => $total,
        ]);
    }

    /**
     * POST /api/community/posts
     * Create new post
     */
    public function storePost(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:5000',
            'body' => 'nullable|string|max:5000',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
        ]);

        $post = CommunityPost::create([
            'user_id' => $user->id,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'body' => $validated['body'] ?? null,
            'category' => $validated['category'] ?? null,
            'tags' => $validated['tags'] ?? [],
        ]);

        return response()->json([
            'message' => 'تم إنشاء المنشور',
            'post' => $post->load('user'),
        ], 201);
    }

    /**
     * POST /api/community/posts/{post}/like
     * Toggle post like and create notification
     */
    public function togglePostLike(Request $request, CommunityPost $post)
    {
        $user = Auth::user();

        $existing = CommunityPostLike::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $post->decrement('likes_count');
            $liked = false;
        } else {
            CommunityPostLike::create([
                'user_id' => $user->id,
                'post_id' => $post->id,
            ]);
            $post->increment('likes_count');
            $liked = true;

            // Create notification for post author
            if ($post->user_id !== $user->id) {
                Notification::create([
                    'user_id' => $post->user_id,
                    'from_user_id' => $user->id,
                    'from_user_name' => $user->name,
                    'title' => 'إعجاب على منشورك',
                    'type' => 'post_like',
                    'entity_id' => $post->id,
                    'entity_title' => $post->title,
                    'message' => "{$user->name} أعجب بمنشورك",
                ]);
            }
        }

        return response()->json([
            'liked' => $liked,
            'likesCount' => $post->fresh()->likes_count,
        ]);
    }

    /**
     * GET /api/community/posts/{post}/comments
     * Get post comments
     */
    public function getComments(Request $request, CommunityPost $post)
    {
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);

        $comments = $post->comments()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $total = $post->comments()->count();

        return response()->json([
            'comments' => $comments,
            'total' => $total,
        ]);
    }

    /**
     * POST /api/community/posts/{post}/comments
     * Add comment to post
     */
    public function storeComment(Request $request, CommunityPost $post)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $comment = $post->comments()->create([
            'user_id' => $user->id,
            'content' => $validated['content'],
        ]);

        $post->increment('comments_count');

        // Create notification for post author
        if ($post->user_id !== $user->id) {
            Notification::create([
                'user_id' => $post->user_id,
                'from_user_id' => $user->id,
                'from_user_name' => $user->name,
                'title' => 'تعليق على منشورك',
                'type' => 'post_comment',
                'entity_id' => $post->id,
                'entity_title' => $post->title,
                'message' => "{$user->name} علق على منشورك",
            ]);
        }

        return response()->json([
            'message' => 'تم إضافة التعليق',
            'comment' => $comment->load('user'),
        ], 201);
    }

    /**
     * DELETE /api/community/posts/{post}/comments/{comment}
     * Delete comment from post
     */
    public function deleteComment(Request $request, CommunityPost $post, CommunityComment $comment)
    {
        $user = Auth::user();

        // Only author or admin can delete
        if ($comment->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $comment->delete();
        $post->decrement('comments_count');

        return response()->json(['message' => 'تم حذف التعليق']);
    }
}

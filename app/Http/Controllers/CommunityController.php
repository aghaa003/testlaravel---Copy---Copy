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
    // GET /api/community/posts
    public function getPosts(Request $request)
    {
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);
        $category = $request->input('category');
        $userId = Auth::id();

        $query = CommunityPost::with('user');

        if ($category) {
            $query->where('category', $category);
        }

        $posts = $query->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(function ($post) use ($userId) {
                $liked = $userId
                    ? CommunityPostLike::where('user_id', $userId)
                        ->where('post_id', $post->id)
                        ->exists()
                    : false;
                $post->setAttribute('liked', $liked);
                $post->setAttribute('likesCount', $post->likes_count);

                return $post;
            });

        // ✅ Fix 1: return flat array
        return response()->json($posts);
    }

    // POST /api/community/posts
    public function storePost(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            // ✅ Fix 2: accept both 'content' and 'body'
            'content' => 'nullable|string|max:5000',
            'body' => 'nullable|string|max:5000',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
        ]);

        $content = $validated['body'] ?? $validated['content'] ?? '';

        $post = CommunityPost::create([
            'user_id' => $user->id,
            'title' => $validated['title'],
            'content' => $content,
            'body' => $content,
            'category' => $validated['category'] ?? null,
            'tags' => $validated['tags'] ?? [],
        ]);

        // ✅ Fix 3: return flat post with user, not { message, post }
        $post->load('user');
        $post->setAttribute('liked', false);
        $post->setAttribute('likesCount', 0);

        return response()->json($post, 201);
    }

    // POST /api/community/posts/{post}/like
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

    // GET /api/community/posts/{post}/comments
    public function getComments(Request $request, CommunityPost $post)
    {
        $comments = $post->comments()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        // ✅ Fix 4: return flat array
        return response()->json($comments);
    }

    // POST /api/community/posts/{post}/comments
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

        // ✅ Fix 5: return flat comment with user
        return response()->json($comment->load('user'), 201);
    }

    // DELETE /api/community/posts/{post}/comments/{comment}
    public function deleteComment(Request $request, CommunityPost $post, CommunityComment $comment)
    {
        $user = Auth::user();

        if ($comment->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $comment->delete();
        $post->decrement('comments_count');

        return response()->json(['message' => 'تم حذف التعليق']);
    }
}

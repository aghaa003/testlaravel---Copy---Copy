<?php

namespace App\Http\Controllers;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityPostLike;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommunityController extends Controller
{
    public function getPosts(Request $request)
    {
        $limit = $request->input('limit', 20);
        $offset = $request->input('offset', 0);
        $category = $request->input('category');
        $search = $request->input('search');
        $userId = Auth::id();

        $query = CommunityPost::with('user');
        if ($category) {
            $query->where('category', $category);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $posts = $query->orderBy('created_at', 'desc')
            ->skip($offset)->take($limit)->get()
            ->map(function ($post) use ($userId) {
                $liked = $userId
                    ? CommunityPostLike::where('user_id', $userId)->where('post_id', $post->id)->exists()
                    : false;
                $post->setAttribute('liked', $liked);
                $post->setAttribute('likesCount', $post->likes_count);
                $post->setAttribute('commentsCount', $post->comments_count);
                $post->setAttribute('authorName', $post->user->name ?? 'مجهول');
                $post->setAttribute('authorAvatar', $post->user->avatar_url ?? null);
                $post->setAttribute('createdAt', $post->created_at?->toJSON());

                return $post;
            });

        return response()->json($posts);
    }

    public function storePost(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
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

        $post->load('user');
        $post->setAttribute('liked', false);
        $post->setAttribute('likesCount', 0);
        $post->setAttribute('commentsCount', 0);
        $post->setAttribute('authorName', $user->name);
        $post->setAttribute('authorAvatar', $user->avatar_url ?? null);
        $post->setAttribute('createdAt', $post->created_at?->toJSON());

        return response()->json($post, 201);
    }

    public function togglePostLike(Request $request, CommunityPost $post)
    {
        $user = Auth::user();

        // Like row + counter must move together, or the cached count drifts.
        $liked = DB::transaction(function () use ($user, $post) {
            $existing = CommunityPostLike::where('user_id', $user->id)->where('post_id', $post->id)->lockForUpdate()->first();

            if ($existing) {
                $existing->delete();
                $post->decrement('likes_count');

                return false;
            }

            CommunityPostLike::create(['user_id' => $user->id, 'post_id' => $post->id]);
            $post->increment('likes_count');

            if ($post->user_id !== $user->id) {
                Notification::create([
                    'user_id' => $post->user_id, 'from_user_id' => $user->id,
                    'from_user_name' => $user->name, 'title' => 'إعجاب على منشورك',
                    'type' => 'post_like', 'entity_id' => $post->id,
                    'entity_title' => $post->title, 'message' => "{$user->name} أعجب بمنشورك",
                ]);
            }

            return true;
        });

        return response()->json(['liked' => $liked, 'likesCount' => $post->fresh()->likes_count]);
    }

    public function getComments(Request $request, CommunityPost $post)
    {
        $comments = $post->comments()->with('user')->orderBy('created_at', 'asc')
            ->get()->map(fn ($c) => $this->formatComment($c));

        return response()->json($comments);
    }

    public function storeComment(Request $request, CommunityPost $post)
    {
        $user = Auth::user();
        $validated = $request->validate(['content' => 'required|string|max:5000']);

        $comment = $post->comments()->create(['user_id' => $user->id, 'content' => $validated['content']]);
        $post->increment('comments_count');

        if ($post->user_id !== $user->id) {
            Notification::create([
                'user_id' => $post->user_id, 'from_user_id' => $user->id,
                'from_user_name' => $user->name, 'title' => 'تعليق على منشورك',
                'type' => 'post_comment', 'entity_id' => $post->id,
                'entity_title' => $post->title, 'message' => "{$user->name} علق على منشورك",
            ]);
        }

        $comment->load('user');

        return response()->json($this->formatComment($comment), 201);
    }

    public function deleteComment(Request $request, CommunityPost $post, CommunityComment $comment)
    {
        $user = Auth::user();
        if ($comment->user_id !== $user->id && ! in_array($user->role, ['admin', 'employer'], true)) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }
        $comment->delete();
        $post->decrement('comments_count');

        return response()->json(['message' => 'تم حذف التعليق']);
    }

    /** GET /api/admin/community-posts — admin/employer moderation list (search via getPosts already supports it). */
    public function adminGetPosts(Request $request)
    {
        return $this->getPosts($request->merge(['limit' => $request->input('limit', 200)]));
    }

    /** DELETE /api/admin/community-posts/{post} — admin/employer can remove any post. */
    public function deletePost(Request $request, CommunityPost $post)
    {
        $post->comments()->delete();
        $post->likes()->delete();
        $post->delete();

        return response()->json(['message' => 'تم حذف المنشور']);
    }

    private function formatComment(CommunityComment $comment): array
    {
        $data = $comment->toArray();
        $data['authorName'] = $comment->user->name ?? 'مجهول';
        $data['authorAvatar'] = $comment->user->avatar_url ?? null;
        $data['createdAt'] = $data['created_at'] ?? null;

        return $data;
    }
}

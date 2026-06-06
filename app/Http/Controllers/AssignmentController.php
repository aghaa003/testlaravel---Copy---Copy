<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\UserAssignment;
use App\Services\CodeReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'nullable|integer|exists:courses,id',
            'course' => 'nullable|integer|exists:courses,id',
            'language' => 'nullable|string|max:100',
            'user_id' => 'nullable|exists:users,id',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $courseId = $validated['course_id'] ?? $validated['course'] ?? null;
        $userId = $validated['user_id'] ?? $request->user()?->id;

        $query = Assignment::query()->where('is_active', true);

        if ($courseId !== null) {
            $query->where('course_id', $courseId);
        }

        if (! empty($validated['language'])) {
            $query->where('language', $validated['language']);
        }

        $total = (clone $query)->count();
        $assignments = $query
            ->with('course:id,title,category,language')
            ->orderBy('course_id')
            ->orderBy('assignment_order')
            ->skip($validated['offset'] ?? 0)
            ->take($validated['limit'] ?? 50)
            ->get();

        if ($userId) {
            $submissions = UserAssignment::where('user_id', $userId)
                ->whereIn('assignment_id', $assignments->pluck('id'))
                ->get()
                ->keyBy('assignment_id');

            $assignments->transform(function (Assignment $assignment) use ($submissions) {
                $submission = $submissions->get($assignment->id);
                $assignment->setAttribute('user_submission', $submission);
                $assignment->setAttribute('completed', (bool) ($submission?->is_completed));
                $assignment->setAttribute('score', $submission?->score);

                return $assignment;
            });
        }

        return response()->json([
            'assignments' => $assignments,
            'total' => $total,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        // ✅ التحقق من الصلاحيات - فقط employer و admin يمكنهم إنشاء تكليفات (ليس creator)
        if (! in_array($user->role, ['employer', 'admin'])) {
            return response()->json([
                'error' => 'Only employers and admins can create assignments',
                'your_role' => $user->role,
            ], 403);
        }

        $validated = $request->validate([
            'course_id' => 'required|integer|exists:courses,id',
            'title' => 'required|string|max:255',
            'question' => 'required|string',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'difficulty' => 'nullable|integer|min:1|max:5',
            'language' => 'nullable|string|max:100',
            'assignment_order' => 'nullable|integer|min:1',
            'points' => 'nullable|integer|min:0|max:1000',
            'is_active' => 'nullable|boolean',
            'due_date' => 'nullable|date',
        ]);

        $assignment = Assignment::create($validated);

        return response()->json($assignment, 201);
    }

    public function show(Assignment $assignment)
    {
        return response()->json($assignment->load('course:id,title,category,language'));
    }

    public function submit(Request $request, CodeReviewService $reviewer)
    {
        $userId = Auth::id();

        if (! $userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        $validated = $request->validate([
            'assignment_id' => 'required|integer|exists:assignments,id',
            'solution' => 'required|string|max:100000',
            'language' => 'nullable|string|max:100',
        ]);

        $assignment = Assignment::where('is_active', true)->findOrFail($validated['assignment_id']);
        $language = $validated['language'] ?? $assignment->language ?? 'General';
        $review = $reviewer->review($validated['solution'], $language, $assignment->question, 'assignment');
        $score = (int) ($review['score'] ?? 0);
        $completed = ($review['verdict'] ?? 'no') === 'yes' && $score >= 60;

        $submission = DB::transaction(function () use ($validated, $language, $review, $score, $completed) {
            return UserAssignment::updateOrCreate(
                [
                    'user_id' => $validated['user_id'],
                    'assignment_id' => $validated['assignment_id'],
                ],
                [
                    'solution' => $validated['solution'],
                    'language' => $language,
                    'score' => $score,
                    'status' => 'graded',
                    'is_completed' => $completed,
                    'feedback' => $review['explanation'] ?? $review['summary'] ?? null,
                    'submitted_at' => now(),
                    'completed_at' => $completed ? now() : null,
                ]
            );
        });

        return response()->json([
            'success' => true,
            'submission' => $submission,
            ...$review,
        ]);
    }

    public function review(Request $request, CodeReviewService $reviewer)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:100000',
            'language' => 'required|string|max:100',
            'problem' => 'required|string|max:10000',
            'problemTitle' => 'nullable|string|max:255',
        ]);

        return response()->json(
            $reviewer->review($validated['code'], $validated['language'], $validated['problem'], 'assignment')
        );
    }

    public function coursesWithAssignments(Request $request)
    {
        $validated = $request->validate([
            'category' => 'nullable|string|max:100',
        ]);

        $query = DB::table('courses as c')
            ->select(
                'c.id',
                'c.title',
                'c.description',
                'c.category',
                'c.thumbnail_url',
                DB::raw('COUNT(a.id) as assignment_count')
            )
            ->join('assignments as a', 'a.course_id', '=', 'c.id')
            ->where('a.is_active', true)
            ->groupBy('c.id', 'c.title', 'c.description', 'c.category', 'c.thumbnail_url')
            ->havingRaw('COUNT(a.id) > 0')
            ->orderBy('c.title');

        if (! empty($validated['category'])) {
            $query->where('c.category', $validated['category']);
        }

        return response()->json($query->get());
    }
}

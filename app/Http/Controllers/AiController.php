<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Challenge;
use App\Services\CodeReviewService;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function general(Request $request, CodeReviewService $reviewer)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'code' => 'nullable|string|max:100000',
            'language' => 'nullable|string|max:100',
        ]);

        if (! empty($validated['code'])) {
            return response()->json(
                $reviewer->review(
                    $validated['code'],
                    $validated['language'] ?? 'General',
                    $validated['message'],
                    'general'
                )
            );
        }

        return response()->json($reviewer->hint($validated['message'], 'general'));
    }

    public function challenges(Request $request, CodeReviewService $reviewer)
    {
        $validated = $request->validate([
            'mode' => 'nullable|string|in:hint,verify,fix,solution,user_message',
            'challenge_id' => 'nullable|integer|exists:challenges,id',
            'question' => 'nullable|string|max:10000',
            'code' => 'nullable|string|max:100000',
            'language' => 'nullable|string|max:100',
            'message' => 'nullable|string|max:2000',
            'user_message' => 'nullable|string|max:2000',
        ]);

        $mode = $validated['mode'] ?? 'hint';
        $challenge = ! empty($validated['challenge_id']) ? Challenge::find($validated['challenge_id']) : null;
        $problem = $validated['question'] ?? $challenge?->description ?? $validated['message'] ?? '';
        $language = $validated['language'] ?? $challenge?->category ?? 'General';

        if ($mode === 'verify') {
            return response()->json(
                $reviewer->review($validated['code'] ?? '', $language, $problem, 'challenge')
            );
        }

        if ($mode === 'fix' || $mode === 'solution') {
            return response()->json(
                $reviewer->fix($validated['code'] ?? '', $language, $problem)
            );
        }

        return response()->json($reviewer->hint($problem, 'challenge'));
    }

    public function projects(Request $request, CodeReviewService $reviewer)
    {
        $validated = $request->validate([
            'mode' => 'nullable|string|in:hint,verify,fix',
            'assignment_id' => 'nullable|integer|exists:assignments,id',
            'question' => 'nullable|string|max:10000',
            'code' => 'nullable|string|max:100000',
            'language' => 'nullable|string|max:100',
            'message' => 'nullable|string|max:2000',
        ]);

        $mode = $validated['mode'] ?? (! empty($validated['code']) ? 'verify' : 'hint');
        $assignment = ! empty($validated['assignment_id']) ? Assignment::find($validated['assignment_id']) : null;
        $problem = $validated['question'] ?? $assignment?->question ?? $validated['message'] ?? '';
        $language = $validated['language'] ?? $assignment?->language ?? 'General';

        if ($mode === 'verify') {
            return response()->json(
                $reviewer->review($validated['code'] ?? '', $language, $problem, 'project')
            );
        }

        if ($mode === 'fix') {
            return response()->json(
                $reviewer->fix($validated['code'] ?? '', $language, $problem)
            );
        }

        return response()->json($reviewer->hint($problem, 'project'));
    }
}

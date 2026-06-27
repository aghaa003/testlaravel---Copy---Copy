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
            'image' => 'nullable|string|max:20000000', // base64 image of code (optional)
        ]);

        $mode = $validated['mode'] ?? 'hint';
        $challenge = ! empty($validated['challenge_id']) ? Challenge::find($validated['challenge_id']) : null;
        $problem = $validated['question'] ?? $challenge?->description ?? $validated['message'] ?? '';
        $language = $validated['language'] ?? $challenge?->category ?? 'General';
        $code = $this->resolveCode($validated, $reviewer);

        if ($mode === 'verify') {
            // Include the resolved code (e.g. OCR'd from an uploaded image) so the
            // frontend can persist the actual transcribed solution, not a blank one.
            return response()->json([...$reviewer->review($code, $language, $problem, 'challenge'), 'resolvedCode' => $code]);
        }

        if ($mode === 'fix' || $mode === 'solution') {
            return response()->json($reviewer->fix($code, $language, $problem));
        }

        // hint → diagnostic hint (where they went wrong) + Mermaid flowchart
        return response()->json($reviewer->diagnoseHint($problem, $code, $language, 'challenge'));
    }

    /**
     * If an image was uploaded, transcribe it to code via the vision model and
     * prefer that; otherwise use the submitted text code.
     */
    private function resolveCode(array $validated, CodeReviewService $reviewer): string
    {
        $code = $validated['code'] ?? '';
        if (! empty($validated['image'])) {
            $fromImage = $reviewer->extractCodeFromImage($validated['image']);
            if ($fromImage) {
                $code = trim($code) !== '' ? $code."\n".$fromImage : $fromImage;
            }
        }

        return $code;
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
            'image' => 'nullable|string|max:20000000',
        ]);

        $assignment = ! empty($validated['assignment_id']) ? Assignment::find($validated['assignment_id']) : null;
        $problem = $validated['question'] ?? $assignment?->question ?? $validated['message'] ?? '';
        $language = $validated['language'] ?? $assignment?->language ?? 'General';
        $code = $this->resolveCode($validated, $reviewer);
        $mode = $validated['mode'] ?? ($code !== '' ? 'verify' : 'hint');

        if ($mode === 'verify') {
            // Include the resolved code (e.g. OCR'd from an uploaded image) so the
            // frontend can persist the actual transcribed solution, not a blank one.
            return response()->json([...$reviewer->review($code, $language, $problem, 'project'), 'resolvedCode' => $code]);
        }

        if ($mode === 'fix') {
            return response()->json($reviewer->fix($code, $language, $problem));
        }

        return response()->json($reviewer->diagnoseHint($problem, $code, $language, 'project'));
    }
}

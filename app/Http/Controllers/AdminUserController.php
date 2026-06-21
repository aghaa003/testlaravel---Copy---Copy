<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Admin-only full CRUD over user accounts.
 * Every route here is gated by `role:admin` in routes/api.php — employers cannot reach it.
 */
class AdminUserController extends Controller
{
    /** GET /api/admin/users/{user} — full detail incl. courses, challenges (with code) and assignments. */
    public function show(User $user)
    {
        $courses = DB::table('enrollments as e')
            ->join('courses as c', 'c.id', '=', 'e.course_id')
            ->where('e.user_id', $user->id)
            ->select('c.id', 'c.title', 'e.progress', 'e.updated_at as last_accessed')
            ->orderByDesc('e.updated_at')
            ->get();

        // Latest submission per challenge (one row each, includes the submitted code).
        $latest = DB::table('challenge_submissions')
            ->selectRaw('challenge_id, MAX(id) as max_id')
            ->where('user_id', $user->id)
            ->groupBy('challenge_id');

        $challenges = DB::table('challenge_submissions as cs')
            ->joinSub($latest, 'l', fn ($j) => $j->on('cs.id', '=', 'l.max_id'))
            ->join('challenges as ch', 'ch.id', '=', 'cs.challenge_id')
            ->select('ch.id', 'ch.title', 'cs.success', 'cs.score', 'cs.points_earned', 'cs.language', 'cs.solution as submitted_code', 'cs.created_at as last_attempted')
            ->orderByDesc('cs.created_at')
            ->get();

        $assignments = DB::table('user_assignments as ua')
            ->join('assignments as a', 'a.id', '=', 'ua.assignment_id')
            ->where('ua.user_id', $user->id)
            ->select('ua.id as submission_id', 'a.id', 'a.title', 'ua.solution', 'ua.language', 'ua.score', 'ua.status', 'ua.is_completed', 'ua.submitted_at')
            ->orderByDesc('ua.submitted_at')
            ->get();

        return response()->json([
            'user' => $user,
            'courses' => $courses,
            'challenges' => $challenges,
            'assignments' => $assignments,
        ]);
    }

    /** POST /api/admin/users/{user}/password — set a new password for the user. */
    public function setPassword(Request $request, User $user)
    {
        $validated = $request->validate(['password' => 'required|string|min:8']);
        $user->update(['password' => Hash::make($validated['password'])]);

        AuditLogger::log($request, 'set_password', 'User', $user->id);

        return response()->json(['success' => true, 'message' => 'تم تحديث كلمة المرور']);
    }

    /** POST /api/admin/users/{user}/ban — ban or unban (body: {banned: bool}). */
    public function setBan(Request $request, User $user)
    {
        $this->guardSelf($user);
        $validated = $request->validate(['banned' => 'required|boolean']);
        $user->update(['banned' => $validated['banned']]);

        AuditLogger::log($request, 'set_ban', 'User', $user->id, ['banned' => $validated['banned']]);

        return response()->json(['success' => true, 'banned' => $user->banned]);
    }

    /** POST /api/admin/users/{user}/disable — disable or enable (body: {disabled: bool}). */
    public function setDisabled(Request $request, User $user)
    {
        $this->guardSelf($user);
        $validated = $request->validate(['disabled' => 'required|boolean']);
        $user->update(['disabled' => $validated['disabled']]);

        AuditLogger::log($request, 'set_disabled', 'User', $user->id, ['disabled' => $validated['disabled']]);

        return response()->json(['success' => true, 'disabled' => $user->disabled]);
    }

    /** POST /api/admin/users/{user}/score — set the user's total points. */
    public function setScore(Request $request, User $user)
    {
        $validated = $request->validate(['points' => 'required|integer|min:0|max:1000000']);
        $old = $user->points;
        $user->update(['points' => $validated['points']]);

        AuditLogger::log($request, 'set_score', 'User', $user->id, ['from' => $old, 'to' => $validated['points']]);

        return response()->json(['success' => true, 'points' => $user->points]);
    }

    /** PATCH /api/admin/users/{user} — edit any profile information. */
    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:users,username,'.$user->id,
            'email' => 'sometimes|email|max:255|unique:users,email,'.$user->id,
            'bio' => 'sometimes|nullable|string',
            'phone' => 'sometimes|nullable|string|max:50',
            'country' => 'sometimes|nullable|string|max:100',
            'avatar_url' => 'sometimes|nullable|string',
            'github_url' => 'sometimes|nullable|url',
            'linkedin_url' => 'sometimes|nullable|url',
            'website_url' => 'sometimes|nullable|url',
            'skills' => 'sometimes|nullable|array',
        ]);

        $user->update($validated);

        AuditLogger::log($request, 'update_user', 'User', $user->id, ['fields' => array_keys($validated)]);

        return response()->json(['success' => true, 'user' => $user->fresh()]);
    }

    /**
     * POST /api/admin/users/{user}/challenges/{challenge}/grade — set a user's score on a
     * challenge. Reconciles the user's total points when the success state flips.
     */
    public function gradeChallenge(Request $request, User $user, \App\Models\Challenge $challenge)
    {
        $validated = $request->validate(['score' => 'required|integer|min:0|max:100']);
        $score = $validated['score'];
        $newSuccess = $score >= 60;

        DB::transaction(function () use ($user, $challenge, $score, $newSuccess) {
            $wasSuccess = \App\Models\ChallengeSubmission::where('user_id', $user->id)
                ->where('challenge_id', $challenge->id)
                ->where('success', true)
                ->lockForUpdate()
                ->exists();

            $latest = \App\Models\ChallengeSubmission::where('user_id', $user->id)
                ->where('challenge_id', $challenge->id)
                ->orderByDesc('id')
                ->first();

            $payload = [
                'score' => $score,
                'success' => $newSuccess,
                'points_earned' => $newSuccess ? $challenge->points : 0,
                'message' => 'تم التصحيح يدوياً من قبل المسؤول.',
            ];

            if ($latest) {
                $latest->update($payload);
            } else {
                \App\Models\ChallengeSubmission::create($payload + [
                    'user_id' => $user->id,
                    'challenge_id' => $challenge->id,
                    'solution' => '',
                    'language' => $challenge->category ?? 'General',
                ]);
            }

            // Reconcile total points only on a success-state transition.
            if ($newSuccess && ! $wasSuccess) {
                $user->increment('points', $challenge->points);
            } elseif (! $newSuccess && $wasSuccess) {
                $user->decrement('points', min($challenge->points, $user->points));
            }
        });

        AuditLogger::log($request, 'grade_challenge', 'Challenge', $challenge->id, ['user_id' => $user->id, 'score' => $score]);

        return response()->json(['success' => true, 'score' => $score, 'success_flag' => $newSuccess]);
    }

    /** POST /api/admin/assignment-submissions/{submission}/grade — set an assignment submission's score. */
    public function gradeAssignment(Request $request, \App\Models\UserAssignment $submission)
    {
        $validated = $request->validate(['score' => 'required|integer|min:0|max:100']);
        $score = $validated['score'];
        $completed = $score >= 60;

        $submission->update([
            'score' => $score,
            'status' => 'graded',
            'is_completed' => $completed,
            'completed_at' => $completed ? now() : null,
        ]);

        AuditLogger::log($request, 'grade_assignment', 'UserAssignment', $submission->id, ['score' => $score]);

        return response()->json(['success' => true, 'score' => $score, 'is_completed' => $completed]);
    }

    /** DELETE /api/admin/challenge-submissions/{submission} — delete a user's challenge answer. */
    public function deleteChallengeSubmission(Request $request, \App\Models\ChallengeSubmission $submission)
    {
        $id = $submission->id;
        $submission->delete();

        AuditLogger::log($request, 'delete_challenge_submission', 'ChallengeSubmission', $id);

        return response()->json(['success' => true]);
    }

    /** DELETE /api/admin/assignment-submissions/{submission} — delete a user's assignment answer. */
    public function deleteAssignmentSubmission(Request $request, \App\Models\UserAssignment $submission)
    {
        $id = $submission->id;
        $submission->delete();

        AuditLogger::log($request, 'delete_assignment_submission', 'UserAssignment', $id);

        return response()->json(['success' => true]);
    }

    /** POST /api/admin/users/{user}/role — change the user's role. */
    public function setRole(Request $request, User $user)
    {
        $this->guardSelf($user);
        $validated = $request->validate(['role' => 'required|in:user,creator,employer,admin']);

        // Don't let the last admin demote themselves out of existence.
        if ($user->role === 'admin' && $validated['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return response()->json(['error' => 'لا يمكن إزالة آخر مدير في النظام.'], 400);
        }

        $old = $user->role;
        $user->update(['role' => $validated['role']]);

        AuditLogger::log($request, 'set_role', 'User', $user->id, ['from' => $old, 'to' => $validated['role']]);

        return response()->json(['success' => true, 'role' => $user->role]);
    }

    /**
     * DELETE /api/admin/users/{user} — soft-delete the account.
     * SoftDeletes makes User::find() return null for the row, so Auth can no longer
     * resolve them: existing sessions are invalidated and they cannot log in again.
     */
    public function destroy(Request $request, User $user)
    {
        $this->guardSelf($user);

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return response()->json(['error' => 'لا يمكن حذف آخر مدير في النظام.'], 400);
        }

        DB::transaction(function () use ($user) {
            // Reset tokens are one-time credentials — always revoke on deletion.
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete(); // soft delete (sets deleted_at)
        });

        AuditLogger::log($request, 'delete_user', 'User', $user->id, ['email' => $user->email]);

        return response()->json(['success' => true, 'message' => 'تم حذف المستخدم']);
    }

    /**
     * DELETE /api/admin/users/{user}/permanent — irreversibly erase a user row.
     * Only allowed on accounts that are already soft-deleted (forces admins to
     * go through `destroy` first), and never on the last remaining admin.
     * The {user} binding is resolved with trashed rows (see route definition).
     */
    public function destroyPermanently(Request $request, User $user)
    {
        $this->guardSelf($user);

        if (! $user->trashed()) {
            return response()->json(['error' => 'يجب حذف الحساب أولاً (حذف عادي) قبل الحذف النهائي.'], 400);
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return response()->json(['error' => 'لا يمكن حذف آخر مدير في النظام.'], 400);
        }

        $email = $user->email;
        $id = $user->id;
        $user->forceDelete();

        AuditLogger::log($request, 'permanently_delete_user', 'User', $id, ['email' => $email]);

        return response()->json(['success' => true, 'message' => 'تم حذف المستخدم نهائياً']);
    }

    /**
     * POST /api/admin/users/{user}/restore — undo a soft-delete (mistaken deletion).
     * The {user} binding is resolved with trashed rows (see route definition).
     */
    public function restore(Request $request, User $user)
    {
        if (! $user->trashed()) {
            return response()->json(['success' => true, 'message' => 'الحساب نشط بالفعل']);
        }

        $user->restore();

        AuditLogger::log($request, 'restore_user', 'User', $user->id, ['email' => $user->email]);

        return response()->json(['success' => true, 'message' => 'تمت استعادة المستخدم']);
    }

    /** Block an admin from changing the dangerous flags on their own account. */
    private function guardSelf(User $user): void
    {
        abort_if($user->id === auth()->id(), 400, 'لا يمكنك تطبيق هذا الإجراء على حسابك الخاص.');
    }
}

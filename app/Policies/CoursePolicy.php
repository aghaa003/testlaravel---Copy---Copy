<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * Admins bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    /**
     * Only the course creator (or admin, via before) may modify it.
     */
    public function update(User $user, Course $course): bool
    {
        return $user->id === $course->creator_id;
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->id === $course->creator_id;
    }
}

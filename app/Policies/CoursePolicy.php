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

    /**
     * Disable/enable: the creator (own course) OR any employer (admins via before()).
     */
    public function toggleActive(User $user, Course $course): bool
    {
        return $user->id === $course->creator_id || $user->role === 'employer';
    }

    /**
     * Manage a course's content (lessons CRUD): the course creator OR any employer
     * (admins bypass via before()).
     */
    public function manageContent(User $user, Course $course): bool
    {
        return $user->id === $course->creator_id || $user->role === 'employer';
    }
}

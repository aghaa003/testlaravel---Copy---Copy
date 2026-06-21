<?php

namespace App\Policies;

use App\Models\Challenge;
use App\Models\User;

class ChallengePolicy
{
    /**
     * Employers get the same blanket access as admins for challenges — they
     * are co-managers of this content, not just allowed to toggle visibility.
     */
    public function before(User $user, string $ability): ?bool
    {
        return in_array($user->role, ['admin', 'employer'], true) ? true : null;
    }

    public function update(User $user, Challenge $challenge): bool
    {
        return $user->id === $challenge->creator_id;
    }

    public function delete(User $user, Challenge $challenge): bool
    {
        return $user->id === $challenge->creator_id;
    }

    public function toggleActive(User $user, Challenge $challenge): bool
    {
        return $user->id === $challenge->creator_id;
    }
}

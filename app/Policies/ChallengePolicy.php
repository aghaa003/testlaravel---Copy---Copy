<?php

namespace App\Policies;

use App\Models\Challenge;
use App\Models\User;

class ChallengePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function update(User $user, Challenge $challenge): bool
    {
        return $user->id === $challenge->creator_id;
    }

    public function delete(User $user, Challenge $challenge): bool
    {
        return $user->id === $challenge->creator_id;
    }
}

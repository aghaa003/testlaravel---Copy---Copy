<?php

namespace App\Policies;

use App\Models\Repository;
use App\Models\User;

class RepositoryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function update(User $user, Repository $repository): bool
    {
        return $user->id === $repository->owner_id;
    }

    public function delete(User $user, Repository $repository): bool
    {
        return $user->id === $repository->owner_id;
    }
}

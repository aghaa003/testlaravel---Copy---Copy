<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Restrict a public listing to active rows.
     *  - employers/admins may see everything when `include_inactive=1`
     *  - creators may additionally see their own inactive rows
     *  - everyone else only sees is_active = true
     */
    protected function applyActiveScope($query, \Illuminate\Http\Request $request, string $ownerColumn = 'creator_id'): void
    {
        $user = $request->user();
        $wantsAll = $request->boolean('include_inactive') && $user
            && in_array($user->role, ['creator', 'employer', 'admin'], true);

        if (! $wantsAll) {
            $query->where('is_active', true);

            return;
        }

        if ($user->role === 'creator') {
            // creator: all active rows + their own (including disabled)
            $query->where(fn ($q) => $q->where('is_active', true)->orWhere($ownerColumn, $user->id));
        }
        // employer/admin: no restriction (see all)
    }
}

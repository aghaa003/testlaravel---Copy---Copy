<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    /**
     * Record a privileged admin action to admin_audit_logs.
     */
    public static function log(Request $request, string $action, string $targetType, string|int $targetId, array $payload = []): void
    {
        DB::table('admin_audit_logs')->insert([
            'admin_id' => Auth::id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => (string) $targetId,
            'payload' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'ip' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

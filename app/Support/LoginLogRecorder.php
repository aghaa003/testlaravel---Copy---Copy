<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoginLogRecorder
{
    public static function record(
        Request $request,
        string $email,
        string $action,
        ?string $userId = null,
        ?string $firstName = null,
    ): void {
        DB::table('login_logs')->insert([
            'user_id' => $userId,
            'email' => $email,
            'first_name' => $firstName,
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  object  $log  Row from login_logs table
     */
    public static function format(object $log): array
    {
        return [
            'id' => $log->id,
            'userId' => $log->user_id,
            'email' => $log->email,
            'firstName' => $log->first_name,
            'action' => $log->action,
            'ipAddress' => $log->ip_address,
            'userAgent' => $log->user_agent,
            'createdAt' => $log->created_at,
        ];
    }
}

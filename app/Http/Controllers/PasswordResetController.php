<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    // POST /api/password/forgot
    public function forgot(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Always return success to prevent email enumeration
        if (! $user) {
            return response()->json([
                'message' => 'إذا كان البريد الإلكتروني مسجلاً، سيصلك رابط إعادة التعيين.',
            ]);
        }

        // Delete any existing token for this email
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $resetUrl = config('app.frontend_url', 'http://localhost:5173')
            .'/reset-password?token='.$token
            .'&email='.urlencode($request->email);

        // Send email — uses MAIL_MAILER=log in dev so it logs to storage/logs/laravel.log
        Mail::send([], [], function ($message) use ($user, $resetUrl) {
            $message->to($user->email)
                ->subject('إعادة تعيين كلمة المرور')
                ->html("
                    <div dir='rtl' style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <h2 style='color: #4f46e5;'>إعادة تعيين كلمة المرور</h2>
                        <p>مرحباً {$user->name}،</p>
                        <p>تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك.</p>
                        <p>انقر على الرابط أدناه لإعادة تعيين كلمة المرور:</p>
                        <a href='{$resetUrl}'
                           style='display:inline-block;padding:12px 24px;background:#4f46e5;color:#fff;
                                  border-radius:8px;text-decoration:none;font-weight:bold;margin:16px 0;'>
                            إعادة تعيين كلمة المرور
                        </a>
                        <p style='color:#666;font-size:14px;'>ينتهي هذا الرابط بعد 60 دقيقة.</p>
                        <p style='color:#666;font-size:14px;'>إذا لم تطلب هذا، تجاهل هذا البريد.</p>
                    </div>
                ");
        });

        return response()->json([
            'message' => 'إذا كان البريد الإلكتروني مسجلاً، سيصلك رابط إعادة التعيين.',
        ]);
    }

    // POST /api/password/reset
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return response()->json(['message' => 'رمز إعادة التعيين غير صالح أو منتهي الصلاحية.'], 422);
        }

        // Check token expiry — 60 minutes
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json(['message' => 'انتهت صلاحية رمز إعادة التعيين.'], 422);
        }

        if (! Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'رمز إعادة التعيين غير صالح.'], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json(['message' => 'البريد الإلكتروني غير مسجل.'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Delete the token after use
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'تم إعادة تعيين كلمة المرور بنجاح.']);
    }

    // GET /api/password/verify-token
    public function verifyToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record || ! Hash::check($request->token, $record->token)) {
            return response()->json(['valid' => false]);
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            return response()->json(['valid' => false, 'expired' => true]);
        }

        return response()->json(['valid' => true]);
    }
}

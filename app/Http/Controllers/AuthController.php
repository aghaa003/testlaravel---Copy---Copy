<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
// Add at the top with other imports
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // 1. تسجيل مستخدم جديد
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|alpha_dash|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'id' => \Illuminate\Support\Str::uuid(), // استخدام UUID كمعرف فريد
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user', // الدور الافتراضي
            'points' => 0,
        ]);

        // تسجيل الدخول تلقائياً بعد التسجيل
        Auth::login($user);

        return response()->json($user, 201);
    }

    // 2. تسجيل الدخول (Login)
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        // محاولة التحقق من البيانات وتثبيت الجلسة
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الاعتماد المدخلة غير صحيحة.'],
            ]);
        }

        // تجديد معرف الجلسة لمنع هجمات Session Fixation
        $request->session()->regenerate();

        return response()->json(Auth::user());
    }

    // 3. تسجيل الخروج (Logout)
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }

    // 4. جلب بيانات المستخدم الحالي (تستخدم عند تحديث صفحة الـ React)
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\Course;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    // دالة البحث الشامل (تطابق الهيكلية المتوقعة لـ SearchResults)
    public function index(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1',
            'type' => 'nullable|in:all,courses,challenges,repositories,users',
        ]);

        $query = $request->input('q');
        $type = $request->input('type', 'all');

        // تجهيز مصفوفة النتائج الفارغة
        $results = [
            'courses' => [],
            'challenges' => [],
            'repositories' => [],
            'users' => [],
        ];

        // 1. البحث في الدورات
        if ($type === 'all' || $type === 'courses') {
            $results['courses'] = Course::with('creator')
                ->where('title', 'LIKE', "%{$query}%")
                ->orWhere('description', 'LIKE', "%{$query}%")
                ->take(10)->get();
        }

        // 2. البحث في التحديات البرمجية
        if ($type === 'all' || $type === 'challenges') {
            $results['challenges'] = Challenge::where('title', 'LIKE', "%{$query}%")
                ->orWhere('description', 'LIKE', "%{$query}%")
                ->take(10)->get();
        }

        // 3. البحث في مستودعات المشاريع
        if ($type === 'all' || $type === 'repositories') {
            $results['repositories'] = Repository::with('owner')
                ->where('visibility', 'public')
                ->where(function ($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                        ->orWhere('description', 'LIKE', "%{$query}%");
                })->take(10)->get();
        }

        // 4. البحث في المستخدمين
        if ($type === 'all' || $type === 'users') {
            $results['users'] = User::where('name', 'LIKE', "%{$query}%")
                ->orWhere('username', 'LIKE', "%{$query}%")
                ->take(10)->get();
        }

        return response()->json($results);
    }
}

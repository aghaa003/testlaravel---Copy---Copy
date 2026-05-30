<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. استخدام firstOrCreate للمستخدم بناءً على clerk_id
        $admin = User::firstOrCreate(
            ['clerk_id' => 'user_clerk_test_123'], // شرط التحقق من الوجود
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'name' => 'أحمد المبرمج',
                'username' => 'ahmed_dev',
                'email' => 'admin123@gmail.com',
                'avatar_url' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=ahmed',
                'bio' => 'مطور برمجيات وشغوف بالتعليم المستمر.',
                'role' => 'admin',
                'points' => 500,
                'global_rank' => 1,
            ]
        );

        // 2. استخدام firstOrCreate للدورة بناءً على العنوان
        $course = Course::firstOrCreate(
            ['title' => 'دورة تطوير الويب المتكاملة باستخدام Laravel'], // شرط التحقق
            [
                'description' => 'تعلم بناء تطبيقات ويب حقيقية وقابلة للتوسع من الصفر باستخدام إطار العمل لارافيل.',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1534665451596-ac99411797f8',
                'category' => 'Web Development',
                'level' => 'intermediate',
                'language' => 'Arabic',
                'creator_id' => $admin->id,
                'average_rating' => 5.0,
                'total_reviews' => 1,
                'total_lessons' => 2,
                'total_enrollments' => 10,
            ]
        );

        // إضافة الدروس فقط إذا كانت الدورة لا تحتوي على دروس حالياً
        if ($course->lessons()->count() === 0) {
            $course->lessons()->createMany([
                [
                    'title' => 'المقدمة وتجهيز بيئة العمل',
                    'description' => 'في هذا الدرس سنتعرف على متطلبات الدورة وكيفية تثبيت الأدوات.',
                    'video_url' => 'https://www.w3schools.com/html/mov_bbb.mp4',
                    'duration' => 600,
                    'order_num' => 1,
                ],
                [
                    'title' => 'فهم نمط الـ MVC في لارافيل',
                    'description' => 'شرح مفصل لكيفية عمل المسارات والمتحكمات والنماذج.',
                    'video_url' => 'https://www.w3schools.com/html/mov_bbb.mp4',
                    'duration' => 1200,
                    'order_num' => 2,
                ],
            ]);
        }

        // 3. استخدام firstOrCreate للتحدي بناءً على العنوان
        Challenge::firstOrCreate(
            ['title' => 'عكس السلسلة النصية (Reverse a String)'], // شرط التحقق
            [
                'description' => 'اكتب دالة تستقبل نصاً وتُرجعه معكوساً. مثال: "hello" تصبح "olleh".',
                'difficulty' => 'easy',
                'category' => 'Algorithms',
                'points' => 50,
                'total_submissions' => 20,
                'success_rate' => 85.0,
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('role', 'creator')->first();

        $courses = [
            [
                'title' => 'أساسيات HTML وCSS للمبتدئين',
                'description' => 'تعلم بناء صفحات الويب من الصفر باستخدام HTML5 وCSS3 بأسلوب سهل ومبسط.',
                'category' => 'تطوير الويب',
                'level' => 'beginner',
                'language' => 'العربية',
                'lessons' => [
                    ['title' => 'مقدمة في HTML', 'description' => 'ما هو HTML وكيف يعمل', 'duration' => 1200, 'order_num' => 1],
                    ['title' => 'العناصر الأساسية', 'description' => 'تعلم العناصر الأساسية في HTML', 'duration' => 1800, 'order_num' => 2],
                    ['title' => 'مقدمة في CSS', 'description' => 'تنسيق الصفحات باستخدام CSS', 'duration' => 2100, 'order_num' => 3],
                    ['title' => 'الـ Box Model', 'description' => 'فهم نموذج الصندوق في CSS', 'duration' => 1500, 'order_num' => 4],
                    ['title' => 'Flexbox والتخطيط', 'description' => 'إنشاء تخطيطات مرنة', 'duration' => 2400, 'order_num' => 5],
                ],
            ],
            [
                'title' => 'JavaScript من الصفر إلى الاحتراف',
                'description' => 'دورة شاملة في JavaScript تغطي الأساسيات والمفاهيم المتقدمة مع تطبيقات عملية.',
                'category' => 'البرمجة',
                'level' => 'intermediate',
                'language' => 'العربية',
                'lessons' => [
                    ['title' => 'مقدمة في JavaScript', 'description' => 'تاريخ وأهمية JavaScript', 'duration' => 900, 'order_num' => 1],
                    ['title' => 'المتغيرات والأنواع', 'description' => 'var, let, const وأنواع البيانات', 'duration' => 1800, 'order_num' => 2],
                    ['title' => 'الدوال والنطاق', 'description' => 'إنشاء واستخدام الدوال', 'duration' => 2100, 'order_num' => 3],
                    ['title' => 'المصفوفات والكائنات', 'description' => 'التعامل مع البيانات المركبة', 'duration' => 2400, 'order_num' => 4],
                    ['title' => 'الـ DOM والأحداث', 'description' => 'التفاعل مع عناصر الصفحة', 'duration' => 3000, 'order_num' => 5],
                    ['title' => 'الـ Promises وAsync/Await', 'description' => 'البرمجة غير المتزامنة', 'duration' => 2700, 'order_num' => 6],
                ],
            ],
            [
                'title' => 'React.js - بناء تطبيقات حديثة',
                'description' => 'تعلم بناء تطبيقات ويب تفاعلية باستخدام React.js وأحدث مكتباته.',
                'category' => 'تطوير الويب',
                'level' => 'advanced',
                'language' => 'العربية',
                'lessons' => [
                    ['title' => 'مقدمة في React', 'description' => 'لماذا React وكيف تعمل', 'duration' => 1200, 'order_num' => 1],
                    ['title' => 'المكونات والـ Props', 'description' => 'إنشاء مكونات قابلة لإعادة الاستخدام', 'duration' => 2100, 'order_num' => 2],
                    ['title' => 'الـ State والـ Hooks', 'description' => 'إدارة حالة التطبيق', 'duration' => 2700, 'order_num' => 3],
                    ['title' => 'التعامل مع الـ API', 'description' => 'جلب البيانات من الخادم', 'duration' => 2400, 'order_num' => 4],
                ],
            ],
            [
                'title' => 'Python للمبتدئين',
                'description' => 'تعلم لغة Python من الصفر مع تطبيقات عملية في تحليل البيانات والأتمتة.',
                'category' => 'البرمجة',
                'level' => 'beginner',
                'language' => 'العربية',
                'lessons' => [
                    ['title' => 'تثبيت Python والبيئة', 'description' => 'إعداد بيئة التطوير', 'duration' => 900, 'order_num' => 1],
                    ['title' => 'المتغيرات والأنواع', 'description' => 'أنواع البيانات في Python', 'duration' => 1500, 'order_num' => 2],
                    ['title' => 'الشروط والحلقات', 'description' => 'التحكم في تدفق البرنامج', 'duration' => 1800, 'order_num' => 3],
                    ['title' => 'الدوال والوحدات', 'description' => 'تنظيم الكود وإعادة استخدامه', 'duration' => 2100, 'order_num' => 4],
                ],
            ],
        ];

        foreach ($courses as $courseData) {
            $lessons = $courseData['lessons'];
            unset($courseData['lessons']);

            $course = Course::create(array_merge($courseData, [
                'creator_id' => $creator->id,
                'total_lessons' => count($lessons),
                'average_rating' => round(mt_rand(35, 50) / 10, 1),
                'total_reviews' => mt_rand(5, 30),
            ]));

            foreach ($lessons as $lessonData) {
                Lesson::create(array_merge($lessonData, [
                    'course_id' => $course->id,
                ]));
            }
        }
    }
}

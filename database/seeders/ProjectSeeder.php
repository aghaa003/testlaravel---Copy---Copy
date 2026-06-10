<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        if (Project::count() > 0) {
            return;
        }

        $projects = [
            ['track' => 'basics', 'title' => 'آلة حاسبة متقدمة', 'description' => 'بناء آلة حاسبة تدعم العمليات الأساسية والمتقدمة مع واجهة رسومية.', 'difficulty' => 2, 'tags' => ['C++', 'UI', 'OOP'], 'category' => 'آلة حاسبة'],
            ['track' => 'basics', 'title' => 'نظام إدارة المكتبة', 'description' => 'تطوير نظام لإدارة الكتب والأعضاء والإعارات باستخدام قاعدة بيانات.', 'difficulty' => 3, 'tags' => ['C#', 'SQL', 'OOP'], 'category' => 'إدارة بيانات'],
            ['track' => 'basics', 'title' => 'برنامج إدارة الطلاب', 'description' => 'نظام لتسجيل الطلاب وحساب معدلاتهم وعرض التقارير.', 'difficulty' => 2, 'tags' => ['C', 'ملفات', 'هياكل بيانات'], 'category' => 'إدارة سجلات'],
            ['track' => 'basics', 'title' => 'لعبة الأفعى Snake', 'description' => 'تطوير لعبة الأفعى الكلاسيكية باستخدام مفاهيم البرمجة الإجرائية.', 'difficulty' => 3, 'tags' => ['C++', 'رسومات', 'منطق اللعبة'], 'category' => 'تطوير ألعاب'],
            ['track' => 'basics', 'title' => 'محرك SQL مصغر', 'description' => 'بناء محرك SQL بسيط يدعم CREATE TABLE وINSERT وSELECT يدوياً.', 'difficulty' => 4, 'tags' => ['C', 'SQL', 'معالجة نصوص'], 'category' => 'محركات قواعد البيانات'],
            ['track' => 'basics', 'title' => 'مترجم CS50 تسلسلي', 'description' => 'تنفيذ تحديات CS50 كاملة من Week 0 إلى Week 5 في مشروع متسلسل.', 'difficulty' => 3, 'tags' => ['CS50', 'C', 'خوارزميات'], 'category' => 'تحديات CS50'],
            ['track' => 'frontend', 'title' => 'متجر إلكتروني React', 'description' => 'بناء متجر إلكتروني كامل مع سلة تسوق وصفحات المنتجات باستخدام React.', 'difficulty' => 3, 'tags' => ['React', 'TypeScript', 'CSS'], 'category' => 'تطوير ويب'],
            ['track' => 'frontend', 'title' => 'لوحة تحكم إدارية', 'description' => 'تصميم داشبورد تفاعلي مع رسوم بيانية وإحصاءات في الوقت الحقيقي.', 'difficulty' => 3, 'tags' => ['Vue.js', 'Charts', 'Dashboard'], 'category' => 'لوحة تحكم'],
            ['track' => 'frontend', 'title' => 'تطبيق الطقس', 'description' => 'تطبيق يعرض بيانات الطقس من API بتصميم جذاب مع رسوم متحركة.', 'difficulty' => 2, 'tags' => ['React', 'API', 'Tailwind CSS'], 'category' => 'تطبيق ويب'],
            ['track' => 'frontend', 'title' => 'منصة مدونة', 'description' => 'إنشاء منصة تدوين كاملة مع مقالات ونظام تعليقات وتصنيفات.', 'difficulty' => 4, 'tags' => ['Next.js', 'Markdown', 'SEO'], 'category' => 'نظام نشر'],
            ['track' => 'frontend', 'title' => 'مشغل موسيقى', 'description' => 'بناء مشغل موسيقى بواجهة جميلة يدعم قوائم التشغيل والتحكم.', 'difficulty' => 3, 'tags' => ['React', 'Web Audio API', 'CSS'], 'category' => 'وسائط'],
            ['track' => 'frontend', 'title' => 'مولد مخططات ذهنية', 'description' => 'أداة رسم تفاعلية لإنشاء مخططات ذهنية قابلة للتصدير.', 'difficulty' => 4, 'tags' => ['Angular', 'Canvas', 'SVG'], 'category' => 'إنتاجية'],
            ['track' => 'backend', 'title' => 'API تجارة إلكترونية', 'description' => 'بناء RESTful API شامل لمتجر إلكتروني مع المصادقة وإدارة المنتجات.', 'difficulty' => 4, 'tags' => ['Node.js', 'Express.js', 'MongoDB'], 'category' => 'تطوير خادم'],
            ['track' => 'backend', 'title' => 'نظام مصادقة كامل', 'description' => 'تطوير نظام تسجيل دخول شامل مع JWT وتشفير وإعادة تعيين كلمة المرور.', 'difficulty' => 4, 'tags' => ['Python', 'Django', 'PostgreSQL'], 'category' => 'أمان'],
            ['track' => 'backend', 'title' => 'نظام دردشة Real-Time', 'description' => 'خادم دردشة باستخدام WebSockets يدعم غرفاً متعددة ورسائل فورية.', 'difficulty' => 4, 'tags' => ['Node.js', 'Express.js', 'MongoDB'], 'category' => 'الوقت الحقيقي'],
            ['track' => 'backend', 'title' => 'خدمة مصغرة Microservice', 'description' => 'بناء خدمة مصغرة مستقلة مع API Gateway.', 'difficulty' => 5, 'tags' => ['Node.js', 'Laravel', 'Flask'], 'category' => 'معمارية'],
            ['track' => 'backend', 'title' => 'محرك بحث مخصص', 'description' => 'تطوير محرك بحث نصي كامل مع فهرسة وترتيب نتائج.', 'difficulty' => 5, 'tags' => ['Python', 'Django', 'MongoDB'], 'category' => 'بحث ونصوص'],
            ['track' => 'backend', 'title' => 'نظام إدارة ملفات API', 'description' => 'بناء API لرفع وتنزيل وتنظيم الملفات مع Express وMulter.', 'difficulty' => 3, 'tags' => ['Express.js', 'PHP', 'Next.js'], 'category' => 'تخزين ملفات'],
        ];

        foreach ($projects as $project) {
            Project::create($project);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Challenge;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChallengeSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();

        $challenges = [
            [
                'title' => 'مجموع الأرقام',
                'description' => 'اكتب دالة تأخذ مصفوفة من الأرقام وتعيد مجموعها.',
                'difficulty' => 'easy',
                'category' => 'الخوارزميات',
                'points' => 10,
                'section' => 'algorithms',
                'input_format' => 'مصفوفة من الأرقام الصحيحة',
                'output_format' => 'عدد صحيح يمثل المجموع',
                'examples' => json_encode([['input' => '[1,2,3,4,5]', 'output' => '15']]),
                'constraints' => 'حجم المصفوفة بين 1 و 1000',
                'tags' => json_encode(['arrays', 'loops']),
            ],
            [
                'title' => 'عكس النص',
                'description' => 'اكتب دالة تأخذ نصاً وتعيده معكوساً.',
                'difficulty' => 'easy',
                'category' => 'معالجة النصوص',
                'points' => 10,
                'section' => 'algorithms',
                'input_format' => 'نص (string)',
                'output_format' => 'نص معكوس',
                'examples' => json_encode([['input' => '"hello"', 'output' => '"olleh"']]),
                'tags' => json_encode(['strings']),
            ],
            [
                'title' => 'الأعداد الأولية',
                'description' => 'اكتب دالة تتحقق إذا كان عدد ما أولياً أم لا.',
                'difficulty' => 'medium',
                'category' => 'الرياضيات',
                'points' => 20,
                'section' => 'algorithms',
                'input_format' => 'عدد صحيح موجب',
                'output_format' => 'true إذا كان أولياً، false إذا لم يكن',
                'examples' => json_encode([['input' => '7', 'output' => 'true'], ['input' => '4', 'output' => 'false']]),
                'tags' => json_encode(['math', 'loops']),
            ],
            [
                'title' => 'ترتيب الفقاعات',
                'description' => 'نفذ خوارزمية ترتيب الفقاعات لترتيب مصفوفة تصاعدياً.',
                'difficulty' => 'medium',
                'category' => 'الخوارزميات',
                'points' => 25,
                'section' => 'algorithms',
                'input_format' => 'مصفوفة من الأرقام',
                'output_format' => 'مصفوفة مرتبة تصاعدياً',
                'examples' => json_encode([['input' => '[64,34,25,12,22,11,90]', 'output' => '[11,12,22,25,34,64,90]']]),
                'tags' => json_encode(['sorting', 'arrays']),
            ],
            [
                'title' => 'البحث الثنائي',
                'description' => 'نفذ خوارزمية البحث الثنائي للبحث عن عنصر في مصفوفة مرتبة.',
                'difficulty' => 'medium',
                'category' => 'الخوارزميات',
                'points' => 30,
                'section' => 'algorithms',
                'input_format' => 'مصفوفة مرتبة وعنصر للبحث عنه',
                'output_format' => 'فهرس العنصر أو -1 إذا لم يوجد',
                'examples' => json_encode([['input' => '[1,3,5,7,9], 5', 'output' => '2']]),
                'tags' => json_encode(['search', 'arrays']),
            ],
            [
                'title' => 'استعلام SQL لأعلى الدرجات',
                'description' => 'اكتب استعلام SQL يجلب أعلى 3 طلاب من حيث الدرجات من جدول students.',
                'difficulty' => 'easy',
                'category' => 'قواعد البيانات',
                'points' => 15,
                'section' => 'databases',
                'input_format' => 'جدول students يحتوي على: id, name, grade',
                'output_format' => 'أسماء ودرجات أعلى 3 طلاب',
                'examples' => json_encode([['input' => 'جدول students', 'output' => 'SELECT name, grade FROM students ORDER BY grade DESC LIMIT 3']]),
                'tags' => json_encode(['sql', 'databases']),
            ],
            [
                'title' => 'صفحة ويب متجاوبة',
                'description' => 'أنشئ صفحة HTML/CSS بسيطة تحتوي على navbar وقسم hero وثلاث بطاقات.',
                'difficulty' => 'hard',
                'category' => 'تطوير الويب',
                'points' => 50,
                'section' => 'web',
                'input_format' => 'متطلبات التصميم',
                'output_format' => 'كود HTML وCSS كامل',
                'tags' => json_encode(['html', 'css', 'responsive']),
            ],
        ];

        foreach ($challenges as $data) {
            Challenge::create(array_merge($data, [
                'creator_id' => $admin->id,
            ]));
        }
    }
}

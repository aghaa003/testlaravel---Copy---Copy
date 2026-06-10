<?php

namespace Database\Seeders;

use App\Models\Example;
use Illuminate\Database\Seeder;

class ExampleSeeder extends Seeder
{
    public function run(): void
    {
        if (Example::count() > 0) {
            return;
        }

        Example::insert([
            [
                'title' => 'بناء صفحة تسجيل دخول',
                'description' => 'مثال عملي يوضح إنشاء نموذج تسجيل دخول متجاوب مع React وTypeScript.',
                'category' => 'Frontend',
                'code' => <<<'CODE'
import { useState } from "react";

export default function LoginForm() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  return (
    <form>
      <input value={email} onChange={(e) => setEmail(e.target.value)} />
      <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} />
    </form>
  );
}
CODE,
                'install_command' => 'npm install react react-dom',
                'technologies' => json_encode(['React', 'TypeScript']),
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'REST API بسيطة',
                'description' => 'مثال يشرح كيفية إنشاء نقطة نهاية GET باستخدام Node.js وExpress.',
                'category' => 'Backend',
                'code' => <<<'CODE'
import express from "express";

const app = express();

app.get("/api/hello", (_req, res) => {
  res.json({ message: "Hello from API" });
});
CODE,
                'install_command' => 'npm install express',
                'technologies' => json_encode(['Node.js', 'Express']),
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'لوحة بيانات تفاعلية',
                'description' => 'مثال يوضح عرض إحصائيات أساسية باستخدام React ومكونات قابلة لإعادة الاستخدام.',
                'category' => 'Frontend',
                'code' => <<<'CODE'
const stats = [
  { label: "المشاريع", value: 12 },
  { label: "الطلاب", value: 48 },
  { label: "الإنجازات", value: 27 },
];
CODE,
                'install_command' => null,
                'technologies' => json_encode(['React']),
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'استعلام SQL لعرض المستخدمين',
                'description' => 'مثال عملي لقراءة البيانات من جدول المستخدمين بترتيب تنازلي.',
                'category' => 'Backend',
                'code' => <<<'CODE'
SELECT id, name, email
FROM users
ORDER BY created_at DESC;
CODE,
                'install_command' => null,
                'technologies' => json_encode(['MySQL']),
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'قالب صفحة هبوط',
                'description' => 'مثال سريع لصفحة هبوط HTML وCSS مع قسم رئيسي وزر دعوة لاتخاذ إجراء.',
                'category' => 'تطبيقات الجوال',
                'code' => <<<'CODE'
<section class="hero">
  <h1>تعلم البرمجة</h1>
  <button>ابدأ الآن</button>
</section>
CODE,
                'install_command' => null,
                'technologies' => json_encode(['HTML', 'CSS']),
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

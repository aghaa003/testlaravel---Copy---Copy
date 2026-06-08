<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::create([
            'id' => Str::uuid(),
            'name' => 'أحمد المدير',
            'username' => 'admin',
            'email' => 'admin@academy.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'points' => 5000,
            'bio' => 'مدير منصة أكاديمية البرمجة',
        ]);

        // Creator
        User::create([
            'id' => Str::uuid(),
            'name' => 'سارة المعلمة',
            'username' => 'creator1',
            'email' => 'creator@academy.test',
            'password' => Hash::make('password123'),
            'role' => 'creator',
            'points' => 2500,
            'bio' => 'مطورة ويب ومعلمة برمجة',
            'github_url' => 'https://github.com/creator1',
        ]);

        // Employer
        User::create([
            'id' => Str::uuid(),
            'name' => 'شركة التقنية',
            'username' => 'employer1',
            'email' => 'employer@academy.test',
            'password' => Hash::make('password123'),
            'role' => 'employer',
            'points' => 1000,
        ]);

        // Regular users
        $users = [
            ['name' => 'محمد الطالب',   'username' => 'user1',  'email' => 'user1@academy.test',  'points' => 850],
            ['name' => 'فاطمة المبرمجة', 'username' => 'user2', 'email' => 'user2@academy.test',  'points' => 1200],
            ['name' => 'عمر المطور',     'username' => 'user3',  'email' => 'user3@academy.test',  'points' => 430],
            ['name' => 'نور المصممة',    'username' => 'user4',  'email' => 'user4@academy.test',  'points' => 670],
            ['name' => 'خالد البرمجي',  'username' => 'user5',  'email' => 'user5@academy.test',  'points' => 2100],
        ];

        foreach ($users as $data) {
            User::create([
                'id' => Str::uuid(),
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make('password123'),
                'role' => 'user',
                'points' => $data['points'],
            ]);
        }
    }
}

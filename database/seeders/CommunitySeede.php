<?php

namespace Database\Seeders;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommunitySeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        $posts = [
            [
                'title' => 'كيف تتعلم البرمجة بفاعلية؟',
                'content' => 'أريد أن أشارككم تجربتي في تعلم البرمجة. البداية كانت صعبة لكن مع الاستمرارية أصبح الأمر أسهل.',
                'body' => 'أريد أن أشارككم تجربتي في تعلم البرمجة. البداية كانت صعبة لكن مع الاستمرارية أصبح الأمر أسهل. أهم نصيحة: التطبيق العملي.',
                'category' => 'تعلم',
                'tags' => ['برمجة', 'تعلم', 'نصائح'],
            ],
            [
                'title' => 'مشروعي الأول باستخدام React',
                'content' => 'أنجزت مشروعي الأول باستخدام React! كان تحدياً رائعاً تعلمت منه الكثير.',
                'body' => 'أنجزت مشروعي الأول باستخدام React! كان تحدياً رائعاً. المشروع عبارة عن تطبيق قائمة مهام مع إمكانية الحفظ.',
                'category' => 'مشاريع',
                'tags' => ['React', 'JavaScript', 'مشروع'],
            ],
            [
                'title' => 'سؤال: ما أفضل طريقة لتعلم الخوارزميات؟',
                'content' => 'أجد صعوبة في فهم الخوارزميات. هل هناك مصادر أو طرق مقترحة؟',
                'body' => 'أجد صعوبة في فهم الخوارزميات وهياكل البيانات. هل هناك كتب أو مصادر مقترحة للمبتدئين؟',
                'category' => 'أسئلة',
                'tags' => ['خوارزميات', 'تعلم', 'سؤال'],
            ],
            [
                'title' => 'نصائح لمقابلات العمل التقنية',
                'content' => 'بعد تجربة عدة مقابلات تقنية، جمعت أهم النصائح التي ساعدتني.',
                'body' => 'بعد تجربة عدة مقابلات تقنية هذا الشهر، جمعت أهم النصائح: 1. راجع الخوارزميات الأساسية 2. تدرب على LeetCode 3. اشرح تفكيرك بصوت عالٍ.',
                'category' => 'وظائف',
                'tags' => ['مقابلات', 'وظائف', 'نصائح'],
            ],
        ];

        foreach ($posts as $i => $postData) {
            $user = $users[$i % $users->count()];
            $post = CommunityPost::create([
                'user_id' => $user->id,
                'title' => $postData['title'],
                'content' => $postData['content'],
                'body' => $postData['body'],
                'category' => $postData['category'],
                'tags' => $postData['tags'],
                'likes_count' => mt_rand(0, 25),
                'comments_count' => 0,
            ]);

            // Add 2 comments per post
            $commentUsers = $users->shuffle()->take(2);
            foreach ($commentUsers as $commentUser) {
                CommunityComment::create([
                    'post_id' => $post->id,
                    'user_id' => $commentUser->id,
                    'content' => 'شكراً على المشاركة المفيدة! هذا محتوى قيم جداً.',
                ]);
                $post->increment('comments_count');
            }
        }
    }
}

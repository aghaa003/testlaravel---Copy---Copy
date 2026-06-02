<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CodeReviewService
{
    private const CODE_INDICATORS = [
        'function', 'def ', 'class ', 'if ', 'for ', 'while ', 'print', 'console.log',
        'return ', 'var ', 'let ', 'const ', 'int ', 'string ', 'public ', 'private ',
        '#include', 'import ', 'echo ', 'namespace ', 'using ', '=>', '==', '!=', '<=', '>=',
        '<html', '<head', '<body', '<div', '<form', '<input', '<button', '<table', '<script',
        'select ', 'insert ', 'update ', 'delete ', 'create ', 'from ', 'where ', 'join ',
        'color:', 'margin:', 'padding:', 'background:', 'display:', 'position:', 'flex', 'grid',
        '{', '}', '()', ';',
    ];

    private const CHEAT_PHRASES = [
        'my answer is correct',
        'my answer is right',
        'the answer is correct',
        'the code is correct',
        'the code is right',
        'accept my answer',
        'إجابتي صحيحة',
        'الإجابة صحيحة',
        'الحل صحيح',
        'كودي صحيح',
        'الكود صحيح',
        'اقبل الإجابة',
    ];

    private const LANGUAGE_KEYWORDS = [
        'C' => ['int', 'printf', 'scanf', 'return', 'main', 'for', 'while', 'if', 'include'],
        'C++' => ['int', 'cout', 'cin', 'return', 'main', 'class', 'vector', 'namespace', 'include'],
        'C#' => ['console', 'class', 'void', 'using', 'namespace', 'static', 'main', 'string'],
        'SQL' => ['select', 'from', 'where', 'insert', 'update', 'delete', 'create', 'table'],
        'MySQL' => ['select', 'from', 'where', 'create', 'table', 'insert', 'join', 'database'],
        'HTML' => ['html', 'body', 'div', 'head', 'title', 'style', 'script', 'form'],
        'CSS' => ['color', 'background', 'display', 'flex', 'margin', 'padding', 'font', 'border'],
        'JavaScript' => ['function', 'const', 'let', 'var', 'return', 'document', 'console', 'async'],
        'React' => ['import', 'usestate', 'useeffect', 'return', 'component', 'props', 'jsx'],
        'Python' => ['def', 'import', 'print', 'return', 'for', 'while', 'if', 'class', 'self'],
        'Node.js' => ['require', 'module', 'const', 'http', 'fs', 'app', 'express', 'server'],
        'Laravel' => ['route', 'controller', 'model', 'php', 'public', 'return', 'eloquent', 'blade'],
        'PHP' => ['php', 'echo', 'function', 'array', 'return', '$', 'isset', 'include'],
        'MongoDB' => ['db', 'find', 'insert', 'aggregate', 'match', 'group', 'sort', 'collection'],
        'TypeScript' => ['interface', 'type', 'const', 'function', 'import', 'export', 'async', 'class'],
    ];

    public function review(string $code, string $language, string $problem, string $context = 'assignment'): array
    {
        $code = trim($code);
        $language = trim($language) ?: 'General';
        $problem = trim($problem);

        $guard = $this->guardAgainstNonCode($code);
        if ($guard !== null) {
            return $guard;
        }

        $ollama = $this->askOllamaForVerdict($code, $language, $problem, $context);

        return $ollama ?? $this->fallbackReview($code, $language, $problem);
    }

    public function hint(string $problem, string $context = 'assignment'): array
    {
        $problem = trim($problem);
        if ($problem === '') {
            return ['success' => false, 'message' => 'يرجى إرسال السؤال.'];
        }

        $result = $this->callOllama([
            ['role' => 'system', 'content' => 'You are an Arabic programming tutor. Give one short hint only. Do not reveal the full solution.'],
            ['role' => 'user', 'content' => "Context: {$context}\nProblem:\n{$problem}\n\nGive one short Arabic hint."],
        ], 0.4);

        if (! $result['success']) {
            return [
                'success' => true,
                'ai_response' => 'تلميح: قسّم المطلوب إلى خطوات صغيرة ثم اختبر كل خطوة قبل الانتقال لما بعدها.',
            ];
        }

        return [
            'success' => true,
            'ai_response' => 'تلميح: '.mb_substr(trim(preg_replace('/\s+/u', ' ', $result['content'])), 0, 180),
        ];
    }

    public function fix(string $code, string $language, string $problem): array
    {
        $problem = trim($problem);
        if ($problem === '') {
            return ['success' => false, 'message' => 'يرجى إرسال السؤال.'];
        }

        $prompt = trim($code) !== ''
            ? "Fix this {$language} code so it solves the problem. Return code only.\n\nProblem:\n{$problem}\n\nCode:\n{$code}"
            : "Write a complete {$language} solution for this problem. Return code only.\n\nProblem:\n{$problem}";

        $result = $this->callOllama([
            ['role' => 'system', 'content' => 'You are a senior coding assistant. Return only code. No explanation.'],
            ['role' => 'user', 'content' => $prompt],
        ], 0.15);

        if (! $result['success']) {
            return ['success' => false, 'message' => $result['error']];
        }

        return [
            'success' => true,
            'ai_response' => 'تم تجهيز الكود.',
            'fixed_code' => $this->extractCode(trim($result['content'])),
        ];
    }

    private function guardAgainstNonCode(string $code): ?array
    {
        if ($code === '') {
            return [
                'success' => true,
                'isCorrect' => false,
                'score' => 0,
                'summary' => 'يرجى كتابة كود برمجي للحل.',
                'strengths' => [],
                'improvements' => ['اكتب الحل الفعلي بدلاً من تركه فارغاً.'],
                'explanation' => 'لا يمكن تقييم حل فارغ.',
                'hint' => 'ابدأ بكتابة الهيكل الأساسي للحل.',
                'verdict' => 'no',
            ];
        }

        $lower = mb_strtolower($code);
        foreach (self::CHEAT_PHRASES as $phrase) {
            if (mb_stripos($lower, mb_strtolower($phrase)) !== false) {
                return [
                    'success' => true,
                    'isCorrect' => false,
                    'score' => 0,
                    'summary' => 'يجب تقديم كود حقيقي وليس جملة تؤكد صحة الحل.',
                    'strengths' => [],
                    'improvements' => ['اكتب الكود الذي يحل المسألة فعلاً.'],
                    'explanation' => 'النظام لا يقبل عبارات مثل "الكود صحيح" كحل.',
                    'hint' => 'استخدم زر التقييم بعد كتابة كود برمجي كامل.',
                    'verdict' => 'no',
                ];
            }
        }

        $hasIndicator = false;
        foreach (self::CODE_INDICATORS as $indicator) {
            if (mb_stripos($lower, mb_strtolower($indicator)) !== false) {
                $hasIndicator = true;
                break;
            }
        }

        if (! $hasIndicator || mb_strlen(trim($code)) < 5) {
            return [
                'success' => true,
                'isCorrect' => false,
                'score' => 10,
                'summary' => 'النص المرسل لا يبدو كوداً برمجياً.',
                'strengths' => [],
                'improvements' => ['أرسل كوداً يحتوي على تراكيب برمجية واضحة.'],
                'explanation' => 'تم رفض النص قبل إرساله للذكاء الاصطناعي لأنه لا يحتوي على مؤشرات كود كافية.',
                'hint' => 'اكتب دالة أو برنامجاً كاملاً حسب لغة المسألة.',
                'verdict' => 'no',
            ];
        }

        return null;
    }

    private function askOllamaForVerdict(string $code, string $language, string $problem, string $context): ?array
    {
        $result = $this->callOllama([
            ['role' => 'system', 'content' => 'You are a strict code judge. Reply only with JSON: {"verdict":"yes|no","score":0-100,"summary":"Arabic summary","strengths":["..."],"improvements":["..."],"explanation":"Arabic explanation","hint":"Arabic hint if no"}'],
            ['role' => 'user', 'content' => "Context: {$context}\nLanguage: {$language}\nProblem:\n{$problem}\n\nStudent code:\n```{$language}\n{$code}\n```"],
        ], 0.1);

        if (! $result['success']) {
            return null;
        }

        $json = $this->extractJson($result['content']);
        if (! is_array($json)) {
            return null;
        }

        $score = max(0, min(100, (int) ($json['score'] ?? 0)));
        $verdict = strtolower((string) ($json['verdict'] ?? ($score >= 60 ? 'yes' : 'no')));
        $isCorrect = $verdict === 'yes' && $score >= 60;

        return [
            'success' => true,
            'isCorrect' => $isCorrect,
            'score' => $score,
            'summary' => (string) ($json['summary'] ?? ($isCorrect ? 'الحل جيد.' : 'الحل يحتاج إلى تحسين.')),
            'strengths' => array_values((array) ($json['strengths'] ?? [])),
            'improvements' => array_values((array) ($json['improvements'] ?? [])),
            'explanation' => (string) ($json['explanation'] ?? ''),
            'hint' => (string) ($json['hint'] ?? ''),
            'verdict' => $isCorrect ? 'yes' : 'no',
        ];
    }

    private function fallbackReview(string $code, string $language, string $problem): array
    {
        $lines = array_filter(preg_split('/\R/', trim($code)) ?: [], fn ($line) => trim($line) !== '');
        $words = preg_split('/\s+/', trim($code)) ?: [];
        $keywords = self::LANGUAGE_KEYWORDS[$language] ?? self::LANGUAGE_KEYWORDS['JavaScript'];
        $lower = mb_strtolower($code);
        $matches = 0;

        foreach ($keywords as $keyword) {
            if (mb_stripos($lower, mb_strtolower($keyword)) !== false) {
                $matches++;
            }
        }

        $ratio = count($keywords) > 0 ? $matches / count($keywords) : 0;
        $score = count($words) < 4
            ? 20
            : min(85, (int) round(25 + ($ratio * 50) + min(count($lines) * 2, 20)));

        $isCorrect = $score >= 60;

        return [
            'success' => true,
            'isCorrect' => $isCorrect,
            'score' => $score,
            'summary' => $isCorrect ? 'حل جيد مبدئياً.' : 'الحل يحتاج إلى مراجعة وتحسين.',
            'strengths' => $isCorrect ? ["يحتوي على عناصر من لغة {$language}."] : [],
            'improvements' => $isCorrect ? ['اختبر الحل بحالات طرفية إضافية.'] : ["تأكد من استخدام تراكيب لغة {$language} وكتابة حل كامل."],
            'explanation' => 'تم استخدام تقييم احتياطي لأن Ollama غير متاح أو لم يرجع JSON صالحاً.',
            'hint' => $isCorrect ? '' : 'راجع المطلوب واكتب الحل خطوة بخطوة.',
            'verdict' => $isCorrect ? 'yes' : 'no',
        ];
    }

    private function callOllama(array $messages, float $temperature): array
    {
        try {
            $response = Http::timeout(config('ai.timeout'))
                ->connectTimeout(5)
                ->post(rtrim(config('ai.ollama_host'), '/').'/api/chat', [
                    'model' => config('ai.ollama_model'),
                    'messages' => $messages,
                    'stream' => false,
                    'keep_alive' => -1,
                    'options' => ['temperature' => $temperature],
                ]);

            if (! $response->successful()) {
                return ['success' => false, 'error' => 'خدمة Ollama غير متاحة.'];
            }

            $content = (string) data_get($response->json(), 'message.content', '');
            if ($content === '') {
                return ['success' => false, 'error' => 'تعذر استخراج رد Ollama.'];
            }

            return ['success' => true, 'content' => $content];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'تعذر الاتصال بـ Ollama: '.$e->getMessage()];
        }
    }

    private function extractJson(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function extractCode(string $text): string
    {
        if (preg_match('/```[a-zA-Z0-9+\-_.]*\R([\s\S]*?)```/u', $text, $matches)) {
            return trim($matches[1]);
        }

        return trim($text);
    }
}

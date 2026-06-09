<?php

return [
    'ollama_host' => env('OLLAMA_HOST', 'http://localhost:11434'),
    'ollama_model' => env('OLLAMA_MODEL', 'qwen2.5-coder:7b-instruct-q4_K_M'),    'timeout' => (int) env('OLLAMA_TIMEOUT', 45),
];

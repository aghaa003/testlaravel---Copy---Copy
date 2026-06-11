<?php

return [
    'ollama_host' => env('OLLAMA_HOST', 'http://localhost:11434'),
    'ollama_model' => env('OLLAMA_MODEL', 'qwen2.5-coder:7b-instruct-q4_K_M'),
    'timeout' => (int) env('OLLAMA_TIMEOUT', 45),

    // Vision-capable model used to read code from an uploaded image/screenshot.
    // Use an Ollama Cloud free-tier vision model (run `ollama signin` once), e.g.
    // qwen3-vl:* / llama3.2-vision, or a local one if pulled.
    'vision_model' => env('OLLAMA_VISION_MODEL', 'qwen2.5vl:7b'),
    'vision_timeout' => (int) env('OLLAMA_VISION_TIMEOUT', 90),
];

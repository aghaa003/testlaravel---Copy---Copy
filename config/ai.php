<?php

return [
    'ollama_host' => env('OLLAMA_HOST', 'http://localhost:11434'),
    'ollama_model' => env('OLLAMA_MODEL', 'qwen2.5-coder:7b-instruct-q4_K_M'),
    'timeout' => (int) env('OLLAMA_TIMEOUT', 45),

    // Vision-capable model used to read code from an uploaded image/screenshot.
    // Runs on Ollama Cloud (requires `ollama signin` once). qwen3-vl:235b-cloud
    // was retired and the other vision-capable cloud models (qwen3.5, kimi-k2.5/2.6)
    // require a paid subscription — minimax-m3:cloud is free and vision-capable.
    'vision_model' => env('OLLAMA_VISION_MODEL', 'minimax-m3:cloud'),
    'vision_timeout' => (int) env('OLLAMA_VISION_TIMEOUT', 90),
];

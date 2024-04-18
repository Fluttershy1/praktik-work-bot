<?php
return [
    'conversation_cache_time' => 40,

    'user_cache_time' => 30,

    'curl_options' => [],

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'default_additional_parameters' => [
            'parse_mode' => 'Markdown'
        ],
    ],
];

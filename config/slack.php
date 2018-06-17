<?php

return [
    'webhooks' => [
        env('SLACK_WEBHOOK_URL_1') => env('SLACK_WEBHOOK_URL_1_CHANNEL'),
        env('SLACK_WEBHOOK_URL_2') => env('SLACK_WEBHOOK_URL_2_CHANNEL')
    ]
];